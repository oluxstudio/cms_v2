<?php

use App\Jobs\ImportGithubTemplate;
use App\Jobs\InstallTemplateJob;
use App\Jobs\ProcessTemplateUpload;
use App\Livewire\PlatformTemplatesPage;
use App\Models\Site;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\TemplateCreator;
use App\Models\TemplatePurchase;
use App\Models\TemplateUpload;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\TemplateUploads\TemplateUploadPipeline;
use App\Services\TwoFactor;
use App\Support\TemplatePaths;
use App\Templates\TemplateRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

function ptmSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

function ptmTemplate(array $attrs = []): Template
{
    $slug = 'ptm-'.Str::lower(Str::random(8));
    $t = Template::create(array_merge([
        'uuid' => (string) Str::uuid(), 'name' => 'Ptm '.$slug, 'slug' => $slug,
        'status' => 'published', 'source' => 'upload', 'builtin_key' => TemplatePaths::UPLOAD_PREFIX.Str::lower(Str::random(10)),
        'price_cents' => 0, 'currency' => 'gbp',
    ], $attrs));
    $v = $t->versions()->create(['version' => '1.0.0', 'manifest' => [], 'payload' => ['pages' => []], 'status' => 'published']);
    $t->update(['latest_version_id' => $v->id]);

    return $t->fresh();
}

function ptmSiteUsing(Template $t, ?string $versionId = null): SiteTemplate
{
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'ptm-'.uniqid().'.test']);

    return SiteTemplate::create(['site_id' => $site->id, 'template_id' => $t->id, 'template_version_id' => $versionId ?? $t->latest_version_id,
        'name' => $t->name, 'source' => 'catalog', 'applied_at' => now()]);
}

test('new versions bump the minor number past the highest one', function () {
    expect(TemplateUploadPipeline::nextVersion([]))->toBe('1.1.0')
        ->and(TemplateUploadPipeline::nextVersion(['1.0.0']))->toBe('1.1.0')
        ->and(TemplateUploadPipeline::nextVersion(['1.0.0', '1.10.0', '1.9.0']))->toBe('1.11.0')
        ->and(TemplateUploadPipeline::nextVersion(['2.3.1', 'junk']))->toBe('2.4.0');
});

test('hiding takes a template out of the store and showing puts it back as it was', function () {
    $live = ptmTemplate();
    $draft = ptmTemplate(['status' => 'draft']);
    $page = Livewire::actingAs(ptmSuper())->test(PlatformTemplatesPage::class);

    $page->call('hide', $live->id)->call('hide', $draft->id);
    expect($live->fresh()->status)->toBe('archived')
        ->and(Template::publiclyListed()->whereKey($live->id)->exists())->toBeFalse();

    $page->call('show', $live->id)->call('show', $draft->id);
    expect($live->fresh()->status)->toBe('published')
        ->and($draft->fresh()->status)->toBe('draft')
        ->and($live->fresh()->status_before_hide)->toBeNull();
});

test('an unused duplicate is deleted with its versions, uploads and files; sales stay on record', function () {
    $dup = ptmTemplate(['name' => 'Dup '.uniqid(), 'status' => 'draft']);
    $upload = TemplateUpload::create(['user_id' => User::factory()->create()->id, 'for_store' => true, 'key' => $dup->builtin_key,
        'status' => TemplateUpload::READY, 'template_id' => $dup->id]);
    $sale = TemplatePurchase::create(['uuid' => (string) Str::uuid(), 'template_id' => $dup->id, 'user_id' => User::factory()->create()->id,
        'price_cents' => 0, 'currency' => 'gbp', 'status' => 'paid', 'purchased_at' => now()]);
    $appDir = TemplatePaths::uploadsRoot().'/'.$dup->builtin_key;
    File::ensureDirectoryExists($appDir);

    Livewire::actingAs(ptmSuper())->test(PlatformTemplatesPage::class)
        ->call('open', $dup->id)->assertSee('Delete template')
        ->call('deleteTemplate', $dup->id);

    expect(Template::find($dup->id))->toBeNull()
        ->and(TemplateUpload::find($upload->id))->toBeNull()
        ->and(TemplateVersion::where('template_id', $dup->id)->exists())->toBeFalse()
        ->and(File::isDirectory($appDir))->toBeFalse()
        ->and($sale->fresh()->template_id)->toBeNull()
        ->and($sale->fresh()->template_name)->toBe($dup->name);
});

test('a template live sites use, or a built-in one, cannot be deleted; a shared app keeps its files', function () {
    $page = Livewire::actingAs(ptmSuper())->test(PlatformTemplatesPage::class);

    $used = ptmTemplate();
    ptmSiteUsing($used);
    $page->call('deleteTemplate', $used->id);
    expect(Template::find($used->id))->not->toBeNull()
        ->and(PlatformTemplatesPage::deleteBlocker($used))->toContain('hide it instead');

    $key = collect(TemplateRegistry::all())->map->key()->first(fn ($k) => $k !== 'blank');
    if ($key) {
        $builtin = Template::where('slug', $key)->first() ?? ptmTemplate(['source' => 'builtin', 'builtin_key' => $key, 'slug' => $key]);
        expect(PlatformTemplatesPage::deleteBlocker($builtin))->toContain('Built-in');
    }

    $original = ptmTemplate();
    $copy = ptmTemplate(['builtin_key' => $original->builtin_key, 'source' => 'builtin']);
    $appDir = TemplatePaths::uploadsRoot().'/'.$original->builtin_key;
    File::ensureDirectoryExists($appDir);
    $page->call('deleteTemplate', $copy->id);
    expect(Template::find($copy->id))->toBeNull()
        ->and(File::isDirectory($appDir))->toBeTrue();
    File::deleteDirectory($appDir);
});

test('a new version upload reuses the template key and is refused for built-in templates', function () {
    Queue::fake();
    $admin = ptmSuper();
    $t = ptmTemplate();

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('openNewVersion', $t->id)
        ->assertSet('replacingId', $t->id)
        ->assertSee('New version')
        ->set('appZip', UploadedFile::fake()->create('app.zip', 10, 'application/zip'))
        ->call('uploadTemplate')
        ->assertHasNoErrors();

    $upload = TemplateUpload::where('replaces_template_id', $t->id)->firstOrFail();
    expect($upload->key)->toBe($t->builtin_key)->and($upload->name)->toBe($t->name);
    Queue::assertPushed(ProcessTemplateUpload::class);
    @unlink($upload->zipPath());

    $builtin = ptmTemplate(['source' => 'builtin', 'builtin_key' => 'ptm-builtin']);
    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('openNewVersion', $builtin->id)
        ->assertSet('uploading', false);
});

test('finishing a new version adds it to the same template and keeps the admin\'s details', function () {
    $t = ptmTemplate(['name' => 'Admin Named', 'price_cents' => 2500, 'status' => 'published', 'thumbnail_url' => 'https://x.test/t.png']);
    $key = $t->builtin_key;
    $upload = TemplateUpload::create(['user_id' => ptmSuper()->id, 'for_store' => true, 'visibility' => 'public', 'key' => $key,
        'replaces_template_id' => $t->id, 'name' => $t->name, 'status' => TemplateUpload::BUILDING]);
    $pkg = TemplatePaths::packageDir($key);
    File::ensureDirectoryExists("{$pkg}/pages");
    File::put("{$pkg}/template.json", json_encode(['key' => $key, 'name' => 'From The App', 'version' => '1.0.0', 'renderer' => $key]));
    $out = storage_path('framework/testing/ptm-out-'.uniqid());
    File::ensureDirectoryExists($out);
    File::put("{$out}/index.html", '<html></html>');

    app(TemplateUploadPipeline::class)->finish($upload, $out);

    $t->refresh();
    expect($t->versions()->pluck('version')->sort()->values()->all())->toBe(['1.0.0', '1.1.0'])
        ->and($t->latestVersion->version)->toBe('1.1.0')
        ->and($t->name)->toBe('Admin Named')
        ->and($t->price_cents)->toBe(2500)
        ->and($t->status)->toBe('published')
        ->and($t->thumbnail_url)->toBe('https://x.test/t.png')
        ->and(Template::where('slug', $key)->where('id', '!=', $t->id)->exists())->toBeFalse()
        ->and($upload->fresh()->status)->toBe(TemplateUpload::READY);

    File::deleteDirectory(TemplatePaths::uploadsRoot().'/'.$key);
    File::deleteDirectory(TemplatePaths::shellDir($key));
    File::deleteDirectory($out);
});

test('updating sites moves outdated sites to the latest version and re-installs them', function () {
    Queue::fake();
    $t = ptmTemplate();
    $old = $t->latest_version_id;
    $new = $t->versions()->create(['version' => '1.1.0', 'manifest' => [], 'payload' => ['pages' => []], 'status' => 'published']);
    $t->update(['latest_version_id' => $new->id]);
    $outdated = ptmSiteUsing($t, $old);
    $current = ptmSiteUsing($t, $new->id);

    Livewire::actingAs(ptmSuper())->test(PlatformTemplatesPage::class)
        ->call('open', $t->id)->assertSee('is on an older version')
        ->call('updateSites', $t->id);

    expect($outdated->fresh()->template_version_id)->toBe($new->id);
    Queue::assertPushed(InstallTemplateJob::class, 1);
    Queue::assertPushed(InstallTemplateJob::class, fn ($j) => $j->siteTemplateId === $outdated->id);
    expect($current->fresh()->template_version_id)->toBe($new->id);
});

test('a thumbnail can be uploaded and removed from the edit drawer', function () {
    Storage::fake('public');
    $t = ptmTemplate();
    $page = Livewire::actingAs(ptmSuper())->test(PlatformTemplatesPage::class)
        ->call('startEdit', $t->id)
        ->set('thumbnail', UploadedFile::fake()->image('cover.png', 800, 500))
        ->call('saveEdit')
        ->assertHasNoErrors();

    $url = $t->fresh()->thumbnail_url;
    expect($url)->toContain('template-thumbnails/uploaded/');
    Storage::disk('public')->assertExists('template-thumbnails/uploaded/'.Str::afterLast($url, '/'));

    $page->call('startEdit', $t->id)->call('removeThumbnail');
    expect($t->fresh()->thumbnail_url)->toBeNull();
    Storage::disk('public')->assertMissing('template-thumbnails/uploaded/'.Str::afterLast($url, '/'));
});

test('editing an admin store upload keeps the visibility chosen; a client upload stays private', function () {
    $store = ptmTemplate(['visibility' => 'public', 'creator_id' => TemplateCreator::firstOrCreate(['slug' => 'olux-studio'], ['name' => 'Olux Studio'])->id]);
    $client = ptmTemplate(['status' => 'private', 'visibility' => 'private', 'creator_id' => null]);
    $page = Livewire::actingAs(ptmSuper())->test(PlatformTemplatesPage::class);

    $page->call('startEdit', $store->id)->set('edit.visibility', 'public')->call('saveEdit')->assertHasNoErrors();
    expect($store->fresh()->visibility)->toBe('public')
        ->and(Template::publiclyListed()->whereKey($store->id)->exists())->toBeTrue();

    $page->call('startEdit', $client->id)->set('edit.visibility', 'public')->call('saveEdit');
    expect($client->fresh()->visibility)->toBe('private');
});

test('update from github builds the next version from the saved repo in the background', function () {
    Queue::fake();
    Http::fake(['api.github.com/*' => Http::response(['name' => 'main'], 200)]);
    $t = ptmTemplate(['source_repo' => 'https://github.com/olux/ptm-repo', 'source_branch' => 'cms-template']);
    $plain = ptmTemplate();
    // The template's original upload already holds its key (the real-world case).
    TemplateUpload::create(['user_id' => User::factory()->create()->id, 'for_store' => true, 'key' => $t->builtin_key,
        'status' => TemplateUpload::READY, 'template_id' => $t->id]);
    $page = Livewire::actingAs(ptmSuper())->test(PlatformTemplatesPage::class);

    $page->call('updateFromGithub', $t->id);
    $upload = TemplateUpload::where('replaces_template_id', $t->id)->latest()->firstOrFail();
    expect($upload->key)->toBe($t->builtin_key)
        ->and($upload->repo_url)->toBe('https://github.com/olux/ptm-repo')
        ->and($upload->repo_branch)->toBe('cms-template');
    Queue::assertPushed(ImportGithubTemplate::class,
        fn ($j) => $j->uploadId === $upload->id && $j->branch === 'cms-template');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/repos/olux/ptm-repo/branches/cms-template'));

    // A second press while it builds doesn't start another.
    $page->call('updateFromGithub', $t->id);
    expect(TemplateUpload::where('replaces_template_id', $t->id)->count())->toBe(1);

    // No repo saved → nothing happens.
    $page->call('updateFromGithub', $plain->id);
    expect(TemplateUpload::where('replaces_template_id', $plain->id)->exists())->toBeFalse();
});

test('the repo is remembered from a github import and can be set in edit', function () {
    $t = ptmTemplate();
    $page = Livewire::actingAs(ptmSuper())->test(PlatformTemplatesPage::class);

    $page->call('startEdit', $t->id)->set('edit.source_repo', 'https://evil.example/x/y')->call('saveEdit')->assertHasErrors('edit.source_repo');
    $page->call('startEdit', $t->id)->set('edit.source_repo', 'https://github.com/olux/ptm-edit.git')->set('edit.source_branch', 'main')->call('saveEdit')->assertHasNoErrors();
    expect($t->fresh()->source_repo)->toBe('https://github.com/olux/ptm-edit')
        ->and($t->fresh()->source_branch)->toBe('main');

    $page->call('openNewVersion', $t->id)->assertSet('addMode', 'github')->assertSet('repoUrl', 'https://github.com/olux/ptm-edit')->assertSet('repoBranch', 'main');
});

test('a mistaken template can be deleted straight from its edit panel', function () {
    $oops = ptmTemplate(['name' => 'Oops '.uniqid(), 'status' => 'draft']);
    $used = ptmTemplate();
    ptmSiteUsing($used);
    $page = Livewire::actingAs(ptmSuper())->test(PlatformTemplatesPage::class);

    $page->call('startEdit', $used->id)->assertSeeHtml('1 site uses this template');
    $page->call('startEdit', $oops->id)->assertSeeHtml("deleteTemplate('{$oops->id}')")
        ->call('deleteTemplate', $oops->id)
        ->assertSet('editingId', null);

    expect(Template::find($oops->id))->toBeNull()->and(Template::find($used->id))->not->toBeNull();
});

test('re-uploading the same app as a zip becomes its next version instead of a duplicate', function () {
    Queue::fake();
    $studio = TemplateCreator::firstOrCreate(['slug' => 'olux-studio'], ['name' => 'Olux Studio']);
    $repoName = 'ptm-zip-'.Str::lower(Str::random(6));
    $t = ptmTemplate(['creator_id' => $studio->id, 'source_repo' => "https://github.com/olux/{$repoName}"]);
    $admin = ptmSuper();

    // GitHub-style archive name of the same repo → matched, shown, and uploaded as a new version.
    $page = Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('openUpload', 'zip')
        ->set('appZip', UploadedFile::fake()->create("{$repoName}-main.zip", 10, 'application/zip'))
        ->assertSet('zipMatchId', $t->id)
        ->assertSee('next version')
        ->call('uploadTemplate')->assertHasNoErrors();
    $v = TemplateUpload::where('replaces_template_id', $t->id)->latest()->first();
    expect($v)->not->toBeNull()->and($v->key)->toBe($t->builtin_key);
    $v->update(['status' => TemplateUpload::READY]);

    // Opting out adds a separate template.
    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('openUpload', 'zip')
        ->set('appZip', UploadedFile::fake()->create("{$repoName}.zip", 10, 'application/zip'))
        ->set('asSeparate', true)
        ->call('uploadTemplate')->assertHasNoErrors();
    expect(TemplateUpload::where('original_filename', "{$repoName}.zip")->whereNull('replaces_template_id')->exists())->toBeTrue();

    // An unrelated zip is a new template.
    expect(PlatformTemplatesPage::zipMatch('something-else-'.uniqid().'.zip'))->toBeNull();
    TemplateUpload::where('user_id', $admin->id)->get()->each(fn ($u) => @unlink($u->zipPath()));
});
