<?php

use App\Jobs\BuildTemplateShell;
use App\Models\Site;
use App\Models\User;
use App\Support\GoLiveChecklist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

function glcFixtureSite(array $attrs = []): Site
{
    $owner = User::factory()->create();

    return Site::create($attrs + [
        'user_id' => $owner->id, 'name' => $n = 'gl-'.uniqid(),
        'domain' => $attrs['domain'] ?? $n.'.test',
        'owner' => $owner->name, 'description' => 't',
    ]);
}

function stageStates(Site $site): array
{
    return collect(GoLiveChecklist::steps($site))->pluck('state', 'key')->all();
}

test('a fresh site shows the journey ahead', function () {
    Queue::fake();
    $site = glcFixtureSite();
    Cache::forget('shell-build:'.$site->renderTemplateKey());

    $states = stageStates($site);

    // No template/pages, no shell yet, not verified, not live ('domain' column is NOT NULL in this schema).
    expect($states['template'])->toBe('active')
        ->and($states['dns'])->toBe('todo')
        ->and($states['live'])->toBe('todo')
        ->and(GoLiveChecklist::progress($site)['complete'])->toBeFalse();
});

test('a bought-and-live site with a built shell is all done', function () {
    $site = glcFixtureSite([
        'template' => 'graceway', // shell exists on disk in dev
        'domain' => 'done-'.uniqid().'.com',
        'domain_verified_at' => now(),
        'live' => true,
    ]);

    $states = stageStates($site);

    expect($states)->toBe([
        'template' => 'done', 'domain' => 'done', 'dns' => 'done', 'live' => 'done',
    ])->and(GoLiveChecklist::progress($site))->toMatchArray(['done' => 4, 'total' => 4, 'complete' => true]);
});

test('ensure() reports ready when the shell exists and queues a build when missing', function () {
    Queue::fake();

    $ready = glcFixtureSite(['template' => 'graceway']);
    if (is_file(public_path('nuxt-preview/graceway/index.html'))) {
        expect(BuildTemplateShell::ensure($ready))->toBe('ready');
        Queue::assertNothingPushed();
    }

    $missing = glcFixtureSite(['template' => 'no-such-template-'.uniqid()]);
    Cache::forget('shell-build:'.$missing->renderTemplateKey());

    // 'blank' fallback shell may exist locally — only assert the queue path when truly missing.
    if ($missing->liveShell() === null) {
        expect(BuildTemplateShell::ensure($missing))->toBe('queued');
        Queue::assertPushed(BuildTemplateShell::class, fn ($job) => $job->templateKey === $missing->renderTemplateKey());

        // Second call must NOT queue again while the first is pending.
        expect(BuildTemplateShell::ensure($missing))->toBe('queued');
        Queue::assertPushed(BuildTemplateShell::class, 1);
    } else {
        expect(BuildTemplateShell::ensure($missing))->toBe('ready');
    }
});
