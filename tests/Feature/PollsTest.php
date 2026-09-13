<?php

use App\Livewire\PollsPage;
use App\Models\AccountMember;
use App\Models\Poll;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteFeature;
use App\Models\User;
use Livewire\Livewire;

function pollsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'poll-'.uniqid(),
        'domain' => 'poll-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $site->enableFeature('polls');

    return [$owner, $site];
}

test('the owner creates a poll with options and manages its lifecycle', function () {
    [$owner, $site] = pollsSite();
    $page = Livewire::actingAs($owner)->test(PollsPage::class, ['site' => $site]);

    $page->set('newQuestion', 'Which new service should we add?')->call('createPoll');
    $poll = Poll::where('site_id', $site->id)->first();
    expect($poll->question)->toBe('Which new service should we add?')
        ->and($page->get('selectedId'))->toBe($poll->id);

    $page->set('newOption', 'Hot stone massage')->call('addOption');
    $page->set('newOption', 'Nail art')->call('addOption');
    expect($poll->options()->count())->toBe(2);

    // Settings: allow multiple + closing date.
    $page->set('pMultiple', true)->set('pEndsAt', now()->addWeek()->format('Y-m-d'))->call('savePoll');
    expect($poll->fresh()->multiple)->toBeTrue()->and($poll->fresh()->ends_at)->not->toBeNull();

    // Close → no longer accepts votes; delete removes everything.
    $page->call('toggleOpen', $poll->id);
    expect($poll->fresh()->acceptsVotes())->toBeFalse();
    $page->call('deletePoll', $poll->id);
    expect(Poll::where('site_id', $site->id)->count())->toBe(0);
});

test('visitors vote once through the public API and see live results', function () {
    [, $site] = pollsSite();
    $poll = Poll::create(['site_id' => $site->id, 'question' => 'Best opening time?', 'slug' => 'best-opening-time']);
    $a = $poll->options()->create(['site_id' => $site->id, 'label' => '8am', 'sort' => 0]);
    $poll->options()->create(['site_id' => $site->id, 'label' => '10am', 'sort' => 1]);

    // Open polls are public config.
    $this->getJson("/api/sites/{$site->name}/polls")->assertOk()
        ->assertJsonPath('polls.0.question', 'Best opening time?')
        ->assertJsonPath('polls.0.options.0.label', '8am');

    // First vote lands; the same visitor voting again is refused.
    $this->postJson("/api/sites/{$site->name}/polls/{$poll->slug}/vote", ['option' => $a->id])
        ->assertStatus(201)->assertJsonPath('options.0.votes', 1);
    $this->postJson("/api/sites/{$site->name}/polls/{$poll->slug}/vote", ['option' => $a->id])
        ->assertStatus(409);
    expect($poll->votes()->count())->toBe(1);

    // A closed poll refuses votes; a feature-less site 404s entirely.
    $poll->update(['is_open' => false]);
    $this->postJson("/api/sites/{$site->name}/polls/{$poll->slug}/vote", ['option' => $a->id])->assertStatus(422);

    [, $plain] = pollsSite();
    SiteFeature::where('site_id', $plain->id)->delete();
    Cache::forget("site_features:{$plain->id}");
    $this->getJson("/api/sites/{$plain->name}/polls")->assertNotFound();
});

test('multi-choice polls accept several options from one visitor', function () {
    [, $site] = pollsSite();
    $poll = Poll::create(['site_id' => $site->id, 'question' => 'Which treatments interest you?', 'slug' => 'treatments', 'multiple' => true]);
    $a = $poll->options()->create(['site_id' => $site->id, 'label' => 'Massage', 'sort' => 0]);
    $b = $poll->options()->create(['site_id' => $site->id, 'label' => 'Facial', 'sort' => 1]);

    $this->postJson("/api/sites/{$site->name}/polls/{$poll->slug}/vote", ['options' => [$a->id, $b->id]])
        ->assertStatus(201);
    expect($poll->votes()->count())->toBe(2);

    // Re-sending the same options doesn't double-count.
    $this->postJson("/api/sites/{$site->name}/polls/{$poll->slug}/vote", ['options' => [$a->id]])->assertStatus(201);
    expect($poll->votes()->count())->toBe(2);
});

test('members without polls.manage cannot create polls and the page is permission-gated', function () {
    [$owner, $site] = pollsSite();
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id]);

    Livewire::actingAs($member)->test(PollsPage::class, ['site' => $site])
        ->set('newQuestion', 'Nope')->call('createPoll')->assertStatus(403);

    // The admin page renders for the owner (route + feature + permission chain).
    $this->actingAs($owner)->get("/{$site->name}/polls")->assertOk()->assertSee('Polls');
});

test('polls report how many PEOPLE voted, distinct from vote count', function () {
    [, $site] = pollsSite();
    $poll = Poll::create(['site_id' => $site->id, 'question' => 'Pick favourites', 'slug' => 'favourites', 'multiple' => true]);
    $a = $poll->options()->create(['site_id' => $site->id, 'label' => 'A', 'sort' => 0]);
    $b = $poll->options()->create(['site_id' => $site->id, 'label' => 'B', 'sort' => 1]);

    // One visitor picks two options: 2 votes but ONE voter.
    $this->postJson("/api/sites/{$site->name}/polls/{$poll->slug}/vote", ['options' => [$a->id, $b->id]])
        ->assertStatus(201)->assertJsonPath('total_votes', 2)->assertJsonPath('total_voters', 1);

    $this->getJson("/api/sites/{$site->name}/polls")->assertOk()
        ->assertJsonPath('polls.0.total_voters', 1)->assertJsonPath('polls.0.total_votes', 2);
    expect($poll->voterCount())->toBe(1);
});

test('a poll opens directly by ID through the URL', function () {
    [$owner, $site] = pollsSite();
    $poll = Poll::create(['site_id' => $site->id, 'question' => 'Deep link me', 'slug' => 'deep-link-me']);

    $this->actingAs($owner)->get("/{$site->name}/polls?poll={$poll->id}")
        ->assertOk()->assertSee('Deep link me');

    Livewire::actingAs($owner)->withQueryParams(['poll' => $poll->id])
        ->test(PollsPage::class, ['site' => $site])
        ->assertSet('selectedId', $poll->id)
        ->assertSet('pQuestion', 'Deep link me');

    // Unknown IDs fail soft — the page opens with no editor selected.
    Livewire::actingAs($owner)->withQueryParams(['poll' => 'nope'])
        ->test(PollsPage::class, ['site' => $site])
        ->assertSet('selectedId', null);
});
