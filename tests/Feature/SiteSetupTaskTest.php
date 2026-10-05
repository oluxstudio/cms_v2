<?php

use App\Livewire\SiteDashboard;
use App\Models\ContentVersion;
use App\Models\Site;
use App\Models\Todo;
use App\Models\User;
use App\Support\SiteProperties;
use App\Support\SiteSetupTask;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

// Site Names are unique across sites: keep each test's sites out of the next run.
uses(DatabaseTransactions::class);

function setupSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'setup-'.uniqid(), 'domain' => 'setup-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site];
}

function setupTask(Site $site): Todo
{
    return Todo::where('site_id', $site->id)->where('system_key', SiteSetupTask::KEY)->with('items')->firstOrFail();
}

test('every new site gets the setup task with the five steps, in order', function () {
    [, $site] = setupSite();

    $task = setupTask($site);
    expect($task->title)->toBe('Set up your site')
        ->and($task->status)->toBe('open')
        ->and($task->items->pluck('label')->all())->toBe([
            'Update site properties', 'Choose a template', 'Update site content', 'Get a domain name', 'Get site live',
        ])
        ->and($task->items->pluck('done')->unique()->all())->toBe([false]);
});

test('steps tick themselves from real data and the task completes when all are done', function () {
    [$owner, $site] = setupSite();

    SiteProperties::save($site, ['values' => ['site_name' => 'Grace Way']]);
    $site->update(['template' => 'graceway']);
    ContentVersion::create(['site_id' => $site->id, 'subject_type' => 'page', 'subject_id' => 'x', 'payload' => [], 'label' => 'x', 'created_by' => $owner->id]);
    SiteSetupTask::sync($site->fresh());

    $done = setupTask($site)->items->pluck('done', 'key')->all();
    expect($done)->toBe(['properties' => true, 'choose_template' => true, 'update_content' => true, 'get_domain' => false, 'go_live' => false]);

    $site->update(['domain_verified_at' => now(), 'live' => true]);
    SiteSetupTask::sync($site->fresh());
    expect(setupTask($site)->status)->toBe('done');
});

test('a step added to the definition later reaches existing tasks; deleted items and tasks stay deleted', function () {
    [, $site] = setupSite();
    $task = setupTask($site);

    // Simulate a site whose task predates the "go live" step.
    $task->items()->where('key', 'go_live')->delete();
    $site->setAttr('setup_task.seeded', json_encode(['properties', 'choose_template', 'update_content', 'get_domain']));
    SiteSetupTask::sync($site->fresh());
    expect(setupTask($site)->items->pluck('key')->last())->toBe('go_live');

    // A person removing a step: not re-added.
    setupTask($site)->items()->where('key', 'get_domain')->delete();
    SiteSetupTask::sync($site->fresh());
    expect(setupTask($site)->items->pluck('key'))->not->toContain('get_domain');

    // Deleting the whole task: not recreated.
    setupTask($site)->delete();
    expect(SiteSetupTask::sync($site->fresh()))->toBeNull()
        ->and(Todo::where('site_id', $site->id)->where('system_key', 'setup')->exists())->toBeFalse();
});

test('the dashboard lists the steps as links to their pages and hides them once complete', function () {
    [$owner, $site] = setupSite();

    Livewire::actingAs($owner)->test(SiteDashboard::class, ['site' => $site])
        ->assertSee('Set up your site')
        ->assertSeeInOrder(['Update site properties', 'Choose a template', 'Update site content', 'Get a domain name', 'Get site live'])
        ->assertSee(url($site->name.'/properties'), false)
        ->assertSee(url($site->name.'/marketplace'), false)
        ->assertSee(url($site->name.'/connect'), false)
        ->assertSee(url($site->name.'/tasks?task='.setupTask($site)->id), false)
        ->assertSet('openTasksCount', 1);

    setupTask($site)->items()->update(['done' => true]);
    Livewire::actingAs($owner)->test(SiteDashboard::class, ['site' => $site->fresh()])
        ->assertDontSee('Update site properties');
});

test('the tasks page opens the setup task from its link and offers each step\'s page', function () {
    [$owner, $site] = setupSite();
    $task = setupTask($site);

    $this->actingAs($owner)->get("/{$site->name}/tasks?task={$task->id}")
        ->assertOk()
        ->assertSee('Set up your site')
        ->assertSee('Open properties');
});
