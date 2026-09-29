<?php

use App\Jobs\CollectTemplateBuild;
use App\Jobs\ProcessTemplateUpload;
use App\Livewire\SiteDesignPage;
use App\Models\Site;
use App\Models\TemplateEntitlement;
use App\Models\TemplateUpload;
use App\Models\User;
use App\Services\TemplateCatalog;
use App\Services\TemplateStager;
use App\Services\TemplateUploads\TemplateUploadPipeline;
use App\Support\TemplatePaths;
use App\Templates\TemplateAppRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function tuAppZip(): string
{
    $files = [
        'package.json' => json_encode(['name' => 'client-app', 'private' => true, 'dependencies' => ['nuxt' => '^4.0.0']]),
        'nuxt.config.ts' => "export default defineNuxtConfig({ ssr: false })\n",
        'app/pages/index.vue' => "<template><HeroBlock /></template>\n<script setup>useHead({ title: 'Home' })</script>\n",
        'app/components/HeroBlock.vue' => "<template><section><h2>Welcome to the client app</h2><p>Editable copy here.</p></section></template>\n",
        'public/assets/stylesheets/styles.css' => ":root { --color-primary: #123456; --color-text: #111111; --font-body: sans-serif; }\n",
    ];
    $path = sys_get_temp_dir().'/tu-'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    foreach ($files as $rel => $content) {
        $zip->addFromString($rel, $content);
    }
    $zip->close();

    return $path;
}

function tuOwnerSite(): array
{
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'tu-'.uniqid().'.test']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site];
}

beforeEach(function () {
    $root = sys_get_temp_dir().'/tu-root-'.uniqid();
    config([
        'templates.uploads.path' => "{$root}/uploads",
        'templates.uploads.builder' => 'sandbox',
        'templates.uploads.sandbox_path' => "{$root}/builds",
    ]);
    $this->tuRoot = $root;
    $this->tuKeys = [];
});

afterEach(function () {
    File::deleteDirectory($this->tuRoot);
    foreach ($this->tuKeys as $key) {
        File::deleteDirectory(TemplatePaths::shellDir($key));
    }
});

test('the Design page accepts a zip, records the upload and queues processing', function () {
    Queue::fake();
    [$owner, $site] = tuOwnerSite();

    Livewire::actingAs($owner)->test(SiteDesignPage::class, ['site' => $site])
        ->set('appZip', UploadedFile::fake()->createWithContent('client.zip', file_get_contents(tuAppZip())))
        ->set('uploadName', 'Spring refresh')
        ->call('uploadApp')
        ->assertHasNoErrors();

    $upload = TemplateUpload::where('site_id', $site->id)->firstOrFail();
    expect($upload->key)->toStartWith(TemplatePaths::UPLOAD_PREFIX)
        ->and($upload->name)->toBe('Spring refresh')
        ->and(File::exists($upload->zipPath()))->toBeTrue();
    Queue::assertPushed(ProcessTemplateUpload::class, fn ($j) => $j->uploadId === $upload->id);
    File::delete($upload->zipPath());

    // Not a zip → rejected before anything is stored.
    Livewire::actingAs($owner)->test(SiteDesignPage::class, ['site' => $site])
        ->set('appZip', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->call('uploadApp')
        ->assertHasErrors('appZip');
});

test('an upload is scanned, handed to the sandbox, and becomes a private library template', function () {
    Queue::fake();
    [$owner, $site] = tuOwnerSite();
    $upload = TemplateUpload::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'key' => 'u-'.strtolower(Str::random(10)),
        'name' => 'Client design', 'status' => TemplateUpload::QUEUED,
    ]);
    $this->tuKeys[] = $upload->key;
    File::ensureDirectoryExists(dirname($upload->zipPath()));
    File::copy(tuAppZip(), $upload->zipPath());

    $pipeline = app(TemplateUploadPipeline::class);
    $pipeline->scanAndPublish($upload->fresh());

    // Published into the CMS contract on the persistent volume, then handed off.
    $upload->refresh();
    $job = config('templates.uploads.sandbox_path')."/jobs/{$upload->key}";
    expect($upload->status)->toBe(TemplateUpload::BUILDING)
        ->and(File::exists(TemplatePaths::packageDir($upload->key).'/template.json'))->toBeTrue()
        ->and(File::exists(TemplatePaths::appDir($upload->key).'/app/plugins/olux-edit.client.ts'))->toBeTrue()
        ->and(File::get("{$job}/REQUEST"))->toBe("/storage/template-shells/{$upload->key}/")
        ->and(File::exists("{$job}/app/node_modules"))->toBeFalse();
    Queue::assertPushed(CollectTemplateBuild::class);

    // Still building → not final.
    expect($pipeline->collect($upload))->toBeFalse();

    // Simulate the sandbox finishing.
    File::ensureDirectoryExists("{$job}/out/assets");
    File::put("{$job}/out/index.html", '<html><link href="/assets/x.css"></html>');
    File::put("{$job}/DONE", '');
    expect($pipeline->collect($upload->fresh()))->toBeTrue();

    $upload->refresh();
    $template = $upload->template;
    expect($upload->status)->toBe(TemplateUpload::READY)
        ->and($template->status)->toBe('private')
        ->and($template->user_id)->toBe($owner->id)
        ->and($template->builtin_key)->toBe($upload->key)
        ->and(TemplatePaths::hasShell($upload->key))->toBeTrue()
        ->and($template->previewUrl($site->name))->toContain("/storage/template-shells/{$upload->key}/?site=")
        ->and(TemplateAppRegistry::exists($upload->key))->toBeTrue()
        ->and(File::get(TemplatePaths::shellDir($upload->key).'/index.html'))->toContain("/storage/template-shells/{$upload->key}/assets/x.css");

    // Private: never in the public store; only the uploader can apply it.
    $ids = app(TemplateCatalog::class)->browse([], 'popular', 100)->pluck('id');
    expect($ids)->not->toContain($template->id);

    $this->actingAs($owner)->postJson("/api/sites/{$site->name}/design/apply", ['template_id' => $template->id])
        ->assertStatus(404); // the JSON API applies published store templates only

    Livewire::actingAs($owner)->test(SiteDesignPage::class, ['site' => $site])
        ->call('applyUpload', $upload->id)
        ->assertDispatched('toast');
    expect($site->fresh()->template)->toBe($upload->key);

    [$stranger, $strangerSite] = tuOwnerSite();
    Livewire::actingAs($stranger)->test(SiteDesignPage::class, ['site' => $strangerSite])
        ->set('selectedId', $template->id)
        ->call('apply')
        ->assertForbidden();
});

test('a failed sandbox build marks the upload failed with the log tail', function () {
    [$owner, $site] = tuOwnerSite();
    $upload = TemplateUpload::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'key' => 'u-'.strtolower(Str::random(10)),
        'status' => TemplateUpload::BUILDING, 'build_started_at' => now(),
    ]);
    $job = config('templates.uploads.sandbox_path')."/jobs/{$upload->key}";
    File::ensureDirectoryExists($job);
    File::put("{$job}/build.log", "npm ERR! missing dependency\nERROR Cannot find module 'foo'\n");
    File::put("{$job}/FAILED", '');

    (new CollectTemplateBuild($upload->id))->handle(app(TemplateUploadPipeline::class));

    $upload->refresh();
    expect($upload->status)->toBe(TemplateUpload::FAILED)
        ->and($upload->error)->toContain("Cannot find module 'foo'");
});

test('a zip with no pages fails with a clear message', function () {
    [$owner, $site] = tuOwnerSite();
    $upload = TemplateUpload::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'key' => 'u-'.strtolower(Str::random(10)), 'status' => TemplateUpload::QUEUED,
    ]);
    $path = sys_get_temp_dir().'/tu-empty-'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('package.json', json_encode(['name' => 'x', 'dependencies' => ['nuxt' => '^4.0.0']]));
    $zip->addFromString('nuxt.config.ts', "export default defineNuxtConfig({})\n");
    $zip->close();
    File::ensureDirectoryExists(dirname($upload->zipPath()));
    File::copy($path, $upload->zipPath());

    (new ProcessTemplateUpload($upload->id))->handle(app(TemplateUploadPipeline::class));

    expect($upload->fresh()->status)->toBe(TemplateUpload::FAILED)
        ->and($upload->fresh()->error)->not->toContain('staging folder');
});

test('uploaded shells deep-link through the SPA fallback', function () {
    $key = 'u-'.strtolower(Str::random(10));
    $this->tuKeys[] = $key;
    File::ensureDirectoryExists(TemplatePaths::shellDir($key));
    File::put(TemplatePaths::shellDir($key).'/index.html', '<html>shell</html>');

    $this->get("/storage/template-shells/{$key}/about-us")->assertOk()->assertHeader('X-Olux-Fallback', '1');
    $this->get('/storage/template-shells/graceway/about-us')->assertNotFound();
});

test('harmless project metadata is skipped, env files are refused', function () {
    $staging = sys_get_temp_dir().'/tu-stage-'.uniqid();
    File::ensureDirectoryExists($staging);
    config(['templates.staging_path' => $staging]);
    $make = function (array $extra) {
        $path = sys_get_temp_dir().'/tu-meta-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ([
            'site/package.json' => json_encode(['name' => 'x', 'dependencies' => ['nuxt' => '^4.0.0']]),
            'site/nuxt.config.ts' => "export default defineNuxtConfig({})\n",
            'site/app/pages/index.vue' => "<template><div/></template>\n",
        ] + $extra as $rel => $content) {
            $zip->addFromString($rel, $content);
        }
        $zip->close();

        return $path;
    };

    $key = 'u-'.strtolower(Str::random(8));
    app(TemplateStager::class)->stageZip($make([
        'site/.gitignore' => "node_modules\n", 'site/LICENSE' => 'MIT', '__MACOSX/site/._index.vue' => 'x',
    ]), $key);
    expect(File::exists("{$staging}/{$key}/package.json"))->toBeTrue()
        ->and(File::exists("{$staging}/{$key}/.gitignore"))->toBeFalse();

    expect(fn () => app(TemplateStager::class)->stageZip($make(['site/.env' => 'DB_PASSWORD=x']), 'u-'.strtolower(Str::random(8))))
        ->toThrow(RuntimeException::class, 'environment files');

    File::deleteDirectory($staging);
});

test('an admin store upload finishes as an Olux Studio draft, not a private library item', function () {
    Queue::fake();
    [$admin] = tuOwnerSite();
    $upload = TemplateUpload::create([
        'user_id' => $admin->id, 'for_store' => true, 'key' => 'u-'.strtolower(Str::random(10)),
        'name' => 'Store Design', 'status' => TemplateUpload::QUEUED,
    ]);
    $this->tuKeys[] = $upload->key;
    File::ensureDirectoryExists(dirname($upload->zipPath()));
    File::copy(tuAppZip(), $upload->zipPath());

    $pipeline = app(TemplateUploadPipeline::class);
    $pipeline->scanAndPublish($upload->fresh());
    $job = config('templates.uploads.sandbox_path')."/jobs/{$upload->key}";
    File::ensureDirectoryExists("{$job}/out");
    File::put("{$job}/out/index.html", '<html></html>');
    File::put("{$job}/DONE", '');
    $pipeline->collect($upload->fresh());

    $template = $upload->fresh()->template;
    expect($template->status)->toBe('draft')
        ->and($template->creator?->slug)->toBe('olux-studio')
        ->and(TemplateEntitlement::where('template_id', $template->id)->exists())->toBeFalse();
});
