<?php

use App\Jobs\Email\CreateMailboxJob;
use App\Jobs\InstallTemplateJob;
use App\Jobs\SiteConnect\CrawlSiteJob;
use App\Livewire\TaskWatcher;
use App\Models\Alert;
use App\Models\EmailDomain;
use App\Models\Mailbox;
use App\Models\PageIngestion;
use App\Models\Site;
use App\Models\SiteTemplate;
use App\Models\User;
use App\Services\SiteConnect\SsrfGuard;
use App\Services\TemplateInstaller;
use App\Support\TaskAlerts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function taSite(?User $owner = null): Site
{
    return Site::factory()->create(['user_id' => ($owner ?? User::factory()->create())->id, 'domain' => 'ta-'.uniqid().'.test']);
}

test('a finished task toasts once on the open page and stays in the bell until read', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $site = taSite($me);
    TaskAlerts::done($me->id, $site->id, 'Template ready: Ta', 'Built.', '/somewhere');
    TaskAlerts::done($other->id, null, 'Not yours');

    $w = Livewire::actingAs($me)->test(TaskWatcher::class)
        ->assertSee('1 unread task notice')
        ->call('poll')
        ->assertDispatched('toast', fn ($name, $p) => $p['title'] === 'Template ready: Ta' && $p['link'] === '/somewhere' && $p['level'] === 'success');

    $w->call('poll')->assertNotDispatched('toast'); // a second tab / poll doesn't repeat it
    $w->call('toggle')->assertSee('Template ready: Ta')->assertDontSee('Not yours');
    $w->call('markAllRead')->assertDontSee('1 unread task notice');

    expect(Alert::forUser($other)->whereNull('toasted_at')->count())->toBe(1);
});

test('task notices with a site also show on that site\'s alerts and fall back to the owner', function () {
    $owner = User::factory()->create();
    $site = taSite($owner);
    $a = TaskAlerts::failed(null, $site->id, 'Design couldn\'t be applied');

    expect($a->user_id)->toBe($owner->id)
        ->and($a->level)->toBe('error')
        ->and(Alert::visibleTo($site, $owner)->whereKey($a->id)->exists())->toBeTrue();
});

test('a design install tells the person who applied it', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $site = taSite($owner);
    $row = SiteTemplate::create(['site_id' => $site->id, 'name' => 'Ta Design', 'source' => 'builtin', 'builtin_key' => 'blank', 'payload' => ['pages' => []]]);

    $installer = Mockery::mock(TemplateInstaller::class);
    $installer->shouldReceive('install')->once();
    (new InstallTemplateJob($site->id, $row->id, $admin->id))->handle($installer);

    expect(Alert::where('user_id', $admin->id)->where('title', 'Design applied: Ta Design')->exists())->toBeTrue();

    (new InstallTemplateJob($site->id, $row->id))->failed(new RuntimeException('boom'));
    expect(Alert::where('user_id', $owner->id)->where('type', 'task_failed')->exists())->toBeTrue();
});

test('a new mailbox tells the person who created it', function () {
    config(['email.driver' => 'fake']);
    $owner = User::factory()->create();
    $site = taSite($owner);
    $domain = 'ta'.uniqid().'.test';
    $d = EmailDomain::create(['account_id' => $owner->id, 'site_id' => $site->id, 'domain' => $domain, 'source' => 'external', 'status' => 'active', 'provider_reference' => 'ref-1']);
    $m = Mailbox::create(['account_id' => $owner->id, 'email_domain_id' => $d->id, 'local_part' => 'hello', 'status' => 'pending', 'created_by' => $owner->id, 'idempotency_key' => uniqid()]);

    app()->call([new CreateMailboxJob($m->id, 'Secret-pass-123'), 'handle']);

    expect($m->fresh()->status)->toBe('active')
        ->and(Alert::where('user_id', $owner->id)->where('title', 'Mailbox ready: hello@'.$domain)->exists())->toBeTrue();
});

test('the bell is on account pages and site pages', function () {
    $owner = User::factory()->create();
    $site = taSite($owner);
    $this->actingAs($owner)->get('/account/subscription')->assertOk()->assertSeeLivewire(TaskWatcher::class);
    $this->actingAs($owner)->get('/'.$site->name.'/dashboard')->assertOk()->assertSeeLivewire(TaskWatcher::class);
});

test('a website import tells the site owner how many pages were fetched', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $site = taSite($owner);
    $host = $site->domain;
    Http::fake(["{$host}/*" => Http::response('<html><body>About</body></html>', 200)]);
    $seed = PageIngestion::create(['site_id' => $site->id, 'source_url' => "https://{$host}/", 'raw_html' => '<html></html>',
        'status' => PageIngestion::STATUS_RECEIVED, 'discovered_links' => ["https://{$host}/about", "https://{$host}/contact"]]);

    $guard = Mockery::mock(SsrfGuard::class);
    $guard->shouldReceive('allows')->andReturnTrue();
    $guard->shouldReceive('pinnedOptions')->andReturn([]);
    (new CrawlSiteJob($site->id, $seed->id))->handle($guard);

    $a = Alert::where('user_id', $owner->id)->where('title', 'Website import finished')->first();
    expect($a)->not->toBeNull()->and($a->body)->toContain('2 pages')->and($a->site_id)->toBe($site->id);
});
