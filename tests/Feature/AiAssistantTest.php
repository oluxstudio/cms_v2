<?php

use App\Contracts\LlmDriverInterface;
use App\Livewire\SitePrompt;
use App\Models\AiChunk;
use App\Models\AiUsage;
use App\Models\Message;
use App\Models\Site;
use App\Models\User;
use App\Services\Ai\SiteKnowledge;
use App\Services\SiteAgent;
use App\Services\SiteTools;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Fakes\FakeLlmDriver;

function aiSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'ai-'.uniqid(), 'domain' => 'ai-'.uniqid().'.test', 'owner' => 'x', 'description' => 'A cosy hair salon in Leeds']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$site, $owner];
}

test('analysis tools return only the asked site\'s numbers', function () {
    [$site, $owner] = aiSite();
    [$other] = aiSite();

    $site->orders()->create(['status' => 'paid', 'total_cents' => 5000, 'currency' => 'gbp', 'paid_at' => now()]);
    $site->orders()->create(['status' => 'pending', 'total_cents' => 900, 'currency' => 'gbp']);
    $other->orders()->create(['status' => 'paid', 'total_cents' => 99900, 'currency' => 'gbp', 'paid_at' => now()]);

    $result = app(SiteTools::class)->execute($site, $owner, 'get_sales_stats', ['period' => '30d']);
    expect($result['ok'])->toBeTrue();
    $data = json_decode($result['message'], true);
    expect($data['orders'])->toBe(2)
        ->and($data['paid_orders'])->toBe(1)
        ->and($data['revenue'])->toContain('50'); // £50, never the other site's £999
});

test('knowledge sync chunks site content, skips unchanged, and keyword retrieval finds it', function () {
    [$site, $owner] = aiSite();
    $site->products()->create(['name' => 'Argan Oil', 'slug' => 'argan-'.uniqid(), 'price_cents' => 2400, 'currency' => 'gbp', 'is_active' => true, 'description' => 'Deeply nourishing argan treatment for damaged hair']);

    $knowledge = app(SiteKnowledge::class); // no Ollama in tests → embeddings null
    $written = $knowledge->sync($site);
    expect($written)->toBeGreaterThan(0)
        ->and(AiChunk::where('site_id', $site->id)->count())->toBeGreaterThan(0);

    // Unchanged content is hash-skipped on the next sync.
    expect($knowledge->sync($site))->toBe(0);

    $chunks = $knowledge->retrieve($site, 'do you have anything with argan for damaged hair?');
    expect(implode(' ', $chunks))->toContain('Argan Oil');

    // Another tenant retrieves nothing from this site's knowledge.
    [$other] = aiSite();
    expect($knowledge->retrieve($other, 'argan oil treatment'))->toBe([]);
});

test('ask() injects retrieved knowledge and records a usage row', function () {
    [$site, $owner] = aiSite();
    Queue::fake();
    $site->products()->create(['name' => 'Bond Repair Mask', 'slug' => 'bond-'.uniqid(), 'price_cents' => 3600, 'currency' => 'gbp', 'is_active' => true, 'description' => 'Rebuilds broken bonds after bleaching']);
    app(SiteKnowledge::class)->sync($site);

    $fake = new FakeLlmDriver(answer: 'We offer the Bond Repair Mask.');
    app()->instance(LlmDriverInterface::class, $fake);

    $result = app(SiteAgent::class)->ask($site, $owner, 'tell me about your bond repair mask treatment');

    expect($result['ok'])->toBeTrue()->and($result['built'])->toBeFalse()
        // RAG rides the USER message; the system prompt stays byte-stable so
        // provider-side prompt caching keeps working.
        ->and($fake->seenSystemPrompts[0])->not->toContain('Bond Repair Mask')
        ->and(end($fake->seenMessages[0])['content'])->toContain('Bond Repair Mask');

    $usage = AiUsage::where('site_id', $site->id)->first();
    expect($usage)->not->toBeNull()
        ->and($usage->input_tokens)->toBe(111)
        ->and($usage->output_tokens)->toBe(42)
        ->and($usage->cache_creation_tokens)->toBe(7)
        ->and($usage->cache_read_tokens)->toBe(9)
        ->and($usage->user_id)->toBe($owner->id);
});

test('a stats tool call counts as read-only (no jump to the builder)', function () {
    [$site, $owner] = aiSite();
    Queue::fake();
    app()->instance(LlmDriverInterface::class, new FakeLlmDriver(answer: 'Sales were £0.', callTool: 'get_sales_stats', toolInput: ['period' => '7d']));

    $result = app(SiteAgent::class)->ask($site, $owner, 'how were sales?');
    expect($result['built'])->toBeFalse()
        ->and($result['tools'])->toBe(['get_sales_stats']);
});

test('the assistant rate limit cools a site down after its hourly allowance', function () {
    [$site, $owner] = aiSite();
    Queue::fake();
    config(['services.llm.per_hour' => 2, 'services.anthropic.key' => 'test-key']);
    app()->instance(LlmDriverInterface::class, new FakeLlmDriver(answer: 'ok'));
    RateLimiter::clear('ai:'.$site->id);

    $lw = Livewire::actingAs($owner)->test(SitePrompt::class, ['siteId' => $site->id]);
    $lw->set('input', 'one')->call('send');
    $lw->set('input', 'two')->call('send');
    $lw->set('input', 'three')->call('send');

    $messages = $lw->get('messages');
    expect(end($messages)['text'])->toContain('cooling down');
});

test('identical history-free questions are served from the answer cache', function () {
    [$site, $owner] = aiSite();
    Queue::fake();
    $fake = new FakeLlmDriver(answer: 'We open at 9am.');
    app()->instance(LlmDriverInterface::class, $fake);

    $first = app(SiteAgent::class)->ask($site, $owner, 'What are your opening hours?');
    $second = app(SiteAgent::class)->ask($site, $owner, 'what are   your opening hours?'); // normalised match
    expect($first)->not->toHaveKey('cached')
        ->and($second['cached'] ?? false)->toBeTrue()
        ->and($second['text'])->toBe('We open at 9am.')
        ->and(count($fake->seenSystemPrompts))->toBe(1); // driver ran once

    // A history-bearing ask must bypass the cache (context-dependent).
    $third = app(SiteAgent::class)->ask($site, $owner, 'What are your opening hours?', [['role' => 'user', 'text' => 'hello']]);
    expect($third['cached'] ?? false)->toBeFalse()
        ->and(count($fake->seenSystemPrompts))->toBe(2);
});

test('time-sensitive turns are never cached and mutations invalidate cached answers', function () {
    [$site, $owner] = aiSite();
    Queue::fake();

    // A stats tool ran → the answer must not be stored.
    app()->instance(LlmDriverInterface::class, new FakeLlmDriver(answer: 'Sales £0.', callTool: 'get_sales_stats', toolInput: ['period' => '7d']));
    app(SiteAgent::class)->ask($site, $owner, 'how were sales this week?');
    $repeat = new FakeLlmDriver(answer: 'Sales £5.', callTool: 'get_sales_stats', toolInput: ['period' => '7d']);
    app()->instance(LlmDriverInterface::class, $repeat);
    expect(app(SiteAgent::class)->ask($site, $owner, 'how were sales this week?')['cached'] ?? false)->toBeFalse();

    // A cached answer goes stale once a mutating tool changes site content.
    $fake = new FakeLlmDriver(answer: 'You have two pages.');
    app()->instance(LlmDriverInterface::class, $fake);
    app(SiteAgent::class)->ask($site, $owner, 'how many pages do I have?');
    app(SiteTools::class)->execute($site, $owner, 'create_task', ['title' => 'Restock towels']); // mutating → bump
    $after = app(SiteAgent::class)->ask($site, $owner, 'how many pages do I have?');
    expect($after['cached'] ?? false)->toBeFalse()
        ->and(count($fake->seenSystemPrompts))->toBe(2); // second ask hit the model again
});

test('the agent can message the team, create tasks, edit content and link pages', function () {
    [$site, $owner] = aiSite();
    $member = User::factory()->create(['name' => 'Amy Helper']);
    $site->members()->syncWithoutDetaching([$member->id => ['role' => 'editor']]);
    $tools = app(SiteTools::class);

    // Broadcast + DM by name.
    expect($tools->execute($site, $owner, 'message_team', ['body' => 'Standup at 9'])['ok'])->toBeTrue();
    expect($tools->execute($site, $owner, 'message_team', ['body' => 'Please restock', 'to' => 'amy'])['ok'])->toBeTrue();
    expect(Message::where('site_id', $site->id)->whereNull('recipient_id')->count())->toBe(1)
        ->and(Message::where('site_id', $site->id)->where('recipient_id', $member->id)->count())->toBe(1);

    // Task with assignee + due date.
    $r = $tools->execute($site, $owner, 'create_task', ['title' => 'Update prices', 'assignee' => 'amy', 'due' => now()->addDays(3)->toDateString(), 'priority' => 'high']);
    expect($r['ok'])->toBeTrue();
    $todo = $site->todos()->first();
    expect($todo->assigned_user_id)->toBe($member->id)
        ->and($todo->priority)->toBe('high')
        ->and($todo->status)->toBe('open');

    // Content editing: list → update → add.
    $component = $site->contentComponents()->create(['name' => 'Hero', 'author' => 'Test', 'source' => 'app']);
    $component->nodes()->create(['label' => 'Headline', 'type' => 'text', 'value' => 'Old headline', 'parent' => '0', 'order' => 0]);

    expect($tools->execute($site, $owner, 'list_components', [])['message'])->toContain('Hero')->toContain('Headline');
    expect($tools->execute($site, $owner, 'update_content', ['component' => 'hero', 'label' => 'headline', 'value' => 'Fresh new headline'])['ok'])->toBeTrue();
    expect($component->nodes()->first()->value)->toBe('Fresh new headline');
    expect($tools->execute($site, $owner, 'add_content', ['component' => 'Hero', 'label' => 'Subheading', 'value' => 'Welcome in'])['ok'])->toBeTrue();
    expect($component->nodes()->count())->toBe(2);

    // Unknown field errors helpfully; page links are read-only.
    expect($tools->execute($site, $owner, 'update_content', ['component' => 'Hero', 'label' => 'Nope', 'value' => 'x'])['ok'])->toBeFalse();
    $site->pages()->create(['name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $links = $tools->execute($site, $owner, 'page_links', []);
    expect($links['ok'])->toBeTrue()->and($links['message'])->toContain('Home');
});
