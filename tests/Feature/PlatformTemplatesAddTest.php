<?php

use App\Jobs\ImportGithubTemplate;
use App\Jobs\ProcessTemplateUpload;
use App\Livewire\PlatformTemplatesPage;
use App\Models\Alert;
use App\Models\Component;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateUpload;
use App\Models\User;
use App\Services\TemplateScaffolder;
use App\Services\TemplateUploads\GithubTemplateFetcher;
use App\Services\TemplateUploads\TemplateUploadPipeline;
use App\Services\TwoFactor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

function ptaSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

function ptaSite(): Site
{
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'pta-'.uniqid().'.test']);
    $page = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $hero = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'System', 'source' => 'app']);
    Node::create(['component_id' => $hero->id, 'label' => 'Heading', 'type' => 'text', 'value' => 'Welcome in', 'parent' => '0', 'order' => 0]);
    $about = Component::create(['site_id' => $site->id, 'name' => 'About', 'author' => 'System', 'source' => 'app']);
    Node::create(['component_id' => $about->id, 'label' => 'Body', 'type' => 'text', 'value' => 'About us', 'parent' => '0', 'order' => 0]);
    $page->components()->attach($about->id, ['order' => 1]);
    $page->components()->attach($hero->id, ['order' => 0]);

    return $site;
}

test('copying a site creates a draft template that mirrors its pages', function () {
    $site = ptaSite();
    $name = 'From site '.uniqid();

    Livewire::actingAs(ptaSuper())->test(PlatformTemplatesPage::class)
        ->call('openUpload', 'site')
        ->call('pickSite', $site->id)
        ->set('fromSiteName', $name)
        ->set('newVisibility', 'private')
        ->call('createFromSite')
        ->assertHasNoErrors();

    $t = Template::where('name', $name)->firstOrFail();
    expect($t->status)->toBe('draft')
        ->and($t->visibility)->toBe('private')
        ->and($t->latest_version_id)->not->toBeNull();

    $pages = $t->versions()->first()->payload['pages'];
    expect($pages)->toHaveCount(1)
        ->and(collect($pages[0]['blocks'])->pluck('name')->all())->toBe(['Hero', 'About'])
        ->and($pages[0]['blocks'][0]['nodes'][0]['value'])->toBe('Welcome in');

    // Applying it to a fresh site scaffolds the same sections and content.
    $fresh = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'pta-'.uniqid().'.test']);
    $fresh->pages()->delete();
    app(TemplateScaffolder::class)->applyPages($fresh, $pages);
    $home = $fresh->pages()->where('url', '/')->firstOrFail();
    expect($home->components()->orderBy('page_component.order')->pluck('components.name')->all())->toBe(['Hero', 'About'])
        ->and($home->components()->where('components.name', 'Hero')->first()->nodes()->value('value'))->toBe('Welcome in');
});

test('copying a site with no pages is refused', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'pta-'.uniqid().'.test']);
    $site->pages()->delete();
    $name = 'Empty '.uniqid();

    Livewire::actingAs(ptaSuper())->test(PlatformTemplatesPage::class)
        ->call('pickSite', $site->id)
        ->set('fromSiteName', $name)
        ->call('createFromSite')
        ->assertHasErrors('fromSiteId');

    expect(Template::where('name', $name)->exists())->toBeFalse();
});

test('the github importer only accepts github repository addresses', function () {
    expect(GithubTemplateFetcher::parse('https://github.com/olux/hairco'))->toMatchArray(['owner' => 'olux', 'repo' => 'hairco'])
        ->and(fn () => GithubTemplateFetcher::parse('https://evil.example/olux/hairco'))->toThrow(RuntimeException::class)
        ->and(fn () => GithubTemplateFetcher::parse('http://169.254.169.254/latest'))->toThrow(RuntimeException::class);
});

test('importing from github checks access at once, then downloads in the background', function () {
    Queue::fake();
    Http::fake(['api.github.com/*' => Http::response(['full_name' => 'olux/pta-repo'], 200)]);
    $admin = ptaSuper();

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('openUpload', 'github')
        ->set('repoUrl', 'https://github.com/olux/pta-repo')
        ->set('newVisibility', 'private')
        ->call('importFromGithub')
        ->assertHasNoErrors()
        ->assertSet('tab', 'uploads');

    $upload = TemplateUpload::where('user_id', $admin->id)->latest()->firstOrFail();
    expect($upload->for_store)->toBeTruthy()->and($upload->name)->toBe('Pta Repo')->and($upload->visibility)->toBe('private')
        ->and(file_exists($upload->zipPath()))->toBeFalse();
    Queue::assertPushed(ImportGithubTemplate::class, fn ($job) => $job->uploadId === $upload->id);
    Queue::assertNotPushed(ProcessTemplateUpload::class);
});

test('the background download hands off to the build, or tells the uploader why it failed', function () {
    Queue::fake();
    $admin = ptaSuper();
    $mk = fn () => TemplateUpload::create(['user_id' => $admin->id, 'for_store' => true, 'key' => 'u-'.Str::lower(Str::random(10)),
        'name' => 'Gh '.uniqid(), 'status' => TemplateUpload::QUEUED]);

    Http::fake([
        'api.github.com/repos/olux/pta-repo/*' => Http::response('PK'.str_repeat('x', 64), 200),
        'api.github.com/repos/olux/gone/*' => Http::response(['message' => 'Not Found'], 404),
    ]);
    $ok = $mk();
    (new ImportGithubTemplate($ok->id, 'https://github.com/olux/pta-repo'))->handle(app(GithubTemplateFetcher::class), app(TemplateUploadPipeline::class));
    expect(file_exists($ok->zipPath()))->toBeTrue();
    Queue::assertPushed(ProcessTemplateUpload::class, fn ($j) => $j->uploadId === $ok->id);
    @unlink($ok->zipPath());

    $bad = $mk();
    (new ImportGithubTemplate($bad->id, 'https://github.com/olux/gone'))->handle(app(GithubTemplateFetcher::class), app(TemplateUploadPipeline::class));
    expect($bad->fresh()->status)->toBe(TemplateUpload::FAILED)
        ->and(Alert::where('user_id', $admin->id)->where('type', 'task_failed')->where('title', 'like', '%'.$bad->name)->exists())->toBeTrue();
});

test('a private repo without a server token explains what is missing', function () {
    config(['templates.git.token' => null]);
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    Livewire::actingAs(ptaSuper())->test(PlatformTemplatesPage::class)
        ->call('openUpload', 'github')
        ->set('repoUrl', 'https://github.com/olux/private-one.git')
        ->set('repoBranch', 'feature/new-look')
        ->call('importFromGithub')
        ->assertHasErrors('repoUrl')
        ->assertSee('TEMPLATES_GIT_TOKEN');

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/repos/olux/private-one/branches/feature/new-look'));
});

test('non super admins cannot add templates', function () {
    Livewire::actingAs(User::factory()->create())->test(PlatformTemplatesPage::class)->assertForbidden();
});

test('a zip upload keeps the chosen visibility and an invalid one is refused', function () {
    Queue::fake();
    $admin = ptaSuper();
    $zip = UploadedFile::fake()->create('app.zip', 10, 'application/zip');

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('openUpload', 'zip')
        ->set('appZip', $zip)
        ->set('newVisibility', 'secret')
        ->call('uploadTemplate')
        ->assertHasErrors('newVisibility')
        ->set('newVisibility', 'private')
        ->call('uploadTemplate')
        ->assertHasNoErrors();

    $upload = TemplateUpload::where('user_id', $admin->id)->latest()->firstOrFail();
    expect($upload->visibility)->toBe('private');
    @unlink($upload->zipPath());
});
