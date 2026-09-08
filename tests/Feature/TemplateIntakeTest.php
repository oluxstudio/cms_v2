<?php

use App\Livewire\TemplateSubmissions;
use App\Models\Site;
use App\Models\TemplateSubmission;
use App\Models\User;
use App\Services\TemplateInstaller;
use App\Services\TemplateStager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function intakeStaging(): string
{
    $dir = sys_get_temp_dir().'/tpl-staging-'.uniqid();
    File::ensureDirectoryExists($dir);
    config(['templates.staging_path' => $dir]);

    return $dir;
}

function conformingAppFiles(): array
{
    return [
        'package.json' => json_encode(['name' => 'fixture', 'private' => true, 'dependencies' => ['nuxt' => '^4.0.0']]),
        'nuxt.config.ts' => "export default defineNuxtConfig({ ssr: false })\n",
        'app/pages/index.vue' => "<template><HeroBlock /></template>\n<script setup>useHead({ title: 'Home' })</script>\n",
        'app/components/HeroBlock.vue' => "<template><section><h2>Welcome to the fixture</h2><p>Editable copy here.</p></section></template>\n",
        'public/assets/stylesheets/styles.css' => ":root { --color-primary: #123456; --color-text: #111111; --font-body: sans-serif; }\n",
    ];
}

function conformingAppZip(?array $files = null): string
{
    $path = sys_get_temp_dir().'/app-'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    foreach ($files ?? conformingAppFiles() as $rel => $content) {
        $zip->addFromString($rel, $content);
    }
    $zip->close();

    return $path;
}

function moderator(): User
{
    $u = User::factory()->create();
    config(['templates.moderators' => [$u->email]]);

    return $u;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/tpl-staging-*') ?: [] as $d) {
        File::deleteDirectory($d);
    }
});

test('a moderator uploads an app zip: staged, scanned, pending; non-moderators cannot', function () {
    $staging = intakeStaging();
    $key = 'fx-'.uniqid();

    Livewire::actingAs(moderator())->test(TemplateSubmissions::class)
        ->set('appZip', UploadedFile::fake()->createWithContent($key.'.zip', file_get_contents(conformingAppZip())))
        ->call('uploadApp');

    expect(File::exists("{$staging}/{$key}/package.json"))->toBeTrue()
        ->and(TemplateSubmission::where('key', $key)->value('status'))->toBe(TemplateSubmission::STATUS_PENDING);

    // Non-moderator: no-op.
    config(['templates.moderators' => ['someone-else@example.com']]);
    Livewire::actingAs(User::factory()->create())->test(TemplateSubmissions::class)->call('scan');
});

test('dangerous zips are rejected: traversal, node_modules, disallowed types', function () {
    $staging = intakeStaging();
    $stager = app(TemplateStager::class);

    foreach ([
        ['../evil.txt' => 'x'] + conformingAppFiles(),
        ['node_modules/x.js' => 'x'] + conformingAppFiles(),
        ['app/pages/run.sh' => 'x'] + conformingAppFiles(),
    ] as $files) {
        $zip = conformingAppZip($files);
        expect(fn () => $stager->stageZip($zip, 'bad-'.uniqid()))->toThrow(RuntimeException::class);
    }
    expect(File::directories($staging))->toBe([]);
});

test('a github-style wrapping folder is stripped on unzip', function () {
    $staging = intakeStaging();
    $wrapped = [];
    foreach (conformingAppFiles() as $rel => $c) {
        $wrapped["repo-main/{$rel}"] = $c;
    }
    $key = 'wrap-'.uniqid();
    app(TemplateStager::class)->stageZip(conformingAppZip($wrapped), $key);
    expect(File::exists("{$staging}/{$key}/package.json"))->toBeTrue()
        ->and(File::isDirectory("{$staging}/{$key}/app/pages"))->toBeTrue();
});

test('template:import stages from a local git repo, records the source, and gates on lint', function () {
    intakeStaging();
    // Build a local bare-ish repo fixture.
    $src = sys_get_temp_dir().'/repo-'.uniqid();
    File::ensureDirectoryExists($src);
    foreach (conformingAppFiles() as $rel => $c) {
        File::ensureDirectoryExists(dirname("{$src}/{$rel}"));
        File::put("{$src}/{$rel}", $c);
    }
    exec("cd {$src} && git init -q && git add -A && git -c user.email=t@t -c user.name=t commit -qm x");

    $key = 'git-'.uniqid();
    $this->artisan('template:import', ['source' => "file://{$src}/.git", '--key' => $key])->assertSuccessful();
    $sub = TemplateSubmission::where('key', $key)->first();
    expect($sub->status)->toBe(TemplateSubmission::STATUS_PENDING)
        ->and($sub->repo_url)->toStartWith('file://');
    File::deleteDirectory($src);

    // Token-less theme → lint error → FAILURE.
    $bad = sys_get_temp_dir().'/bad-'.uniqid();
    foreach (conformingAppFiles() as $rel => $c) {
        File::ensureDirectoryExists(dirname("{$bad}/{$rel}"));
        File::put("{$bad}/{$rel}", $rel === 'public/assets/stylesheets/styles.css' ? 'body { color: red; }' : $c);
    }
    $this->artisan('template:import', ['source' => $bad, '--key' => 'bad-'.uniqid()])->assertFailed();
    File::deleteDirectory($bad);
});

test('the push webhook re-imports known repos and rejects bad signatures', function () {
    intakeStaging();
    config(['templates.git.webhook_secret' => 'whs-test']);
    $sub = TemplateSubmission::create(['key' => 'hook-'.uniqid(), 'name' => 'Hook', 'status' => TemplateSubmission::STATUS_PENDING, 'extraction' => ['pages' => []], 'repo_url' => 'https://example.com/tpl-'.uniqid().'.git']);

    $body = json_encode(['repository' => ['clone_url' => $sub->repo_url]]);
    $sig = 'sha256='.hash_hmac('sha256', $body, 'whs-test');

    Queue::fake();
    $this->call('POST', '/hooks/template-repo', [], [], [], ['HTTP_X-Hub-Signature-256' => $sig, 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
    $this->call('POST', '/hooks/template-repo', [], [], [], ['HTTP_X-Hub-Signature-256' => 'sha256=nope', 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(403);
    $other = json_encode(['repository' => ['clone_url' => 'https://example.com/unknown.git']]);
    $otherSig = 'sha256='.hash_hmac('sha256', $other, 'whs-test');
    $this->call('POST', '/hooks/template-repo', [], [], [], ['HTTP_X-Hub-Signature-256' => $otherSig, 'CONTENT_TYPE' => 'application/json'], $other)->assertStatus(202);
});

test('template:deploy republishes-or-skips, refreshes applied sites, and never clobbers owner edits', function () {
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'dep-'.uniqid(), 'domain' => 'dep-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    // Owner edits a node; a page goes missing (simulates an added-later page).
    $hero = $site->contentComponents()->where('name', 'Hero')->first();
    $node = $hero->nodes()->where('type', 'text')->first();
    if ($node) {
        $node->update(['value' => 'OWNER EDIT']);
    }
    $pages = $site->pages()->count();
    $site->pages()->where('url', '!=', '/')->first()?->delete();

    $this->artisan('template:deploy', ['key' => 'hairco', '--no-build' => true])
        ->expectsOutputToContain($site->name)
        ->assertExitCode(0);

    $site->refresh();
    expect($site->pages()->count())->toBe($pages); // deleted page re-scaffolded
    if ($node) {
        expect($node->fresh()->value)->toBe('OWNER EDIT');
    } // edits survive
});

test('refreshAppliedSites only touches sites on the given template', function () {
    $owner = User::factory()->create();
    $a = Site::create(['user_id' => $owner->id, 'name' => 'ra-'.uniqid(), 'domain' => 'ra-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $b = Site::create(['user_id' => $owner->id, 'name' => 'rb-'.uniqid(), 'domain' => 'rb-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    foreach ([$a, $b] as $s) {
        $s->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    }
    $installer = app(TemplateInstaller::class);
    $installer->apply($a, $installer->saveCuratedToSite($a, 'hairco'));
    $installer->apply($b, $installer->saveCuratedToSite($b, 'verita'));

    $seen = [];
    $installer->refreshAppliedSites('hairco', function ($n) use (&$seen) {
        $seen[] = $n;
    });
    expect($seen)->toContain($a->name)->not->toContain($b->name);
});
