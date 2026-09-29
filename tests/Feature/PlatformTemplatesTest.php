<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Jobs\ProcessTemplateUpload;
use App\Livewire\PlatformTemplatesPage;
use App\Models\Site;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\TemplateUpload;
use App\Models\User;
use App\Services\TwoFactor;
use App\Support\TemplatePaths;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

function ptSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

function ptTemplate(array $attrs = []): Template
{
    $slug = 'pt-'.Str::lower(Str::random(8));

    return Template::create(array_merge([
        'uuid' => (string) Str::uuid(), 'name' => 'Pt '.$slug, 'slug' => $slug,
        'status' => 'published', 'source' => 'builtin', 'price_cents' => 0, 'currency' => 'gbp',
    ], $attrs));
}

test('only a verified super admin reaches the templates page', function () {
    $this->actingAs(User::factory()->create())->get('/admin/templates')->assertForbidden();
    $this->actingAs(ptSuper())->get('/admin/templates')->assertRedirect();
    $this->actingAs(ptSuper())->withSession([EnsureSuperAdmin::SESSION_KEY => now()])
        ->get('/admin/templates')->assertOk()->assertSee('Client uploads');
});

test('the catalog shows which sites use a template and filters by status', function () {
    $admin = ptSuper();
    $tag = 'Zx'.uniqid();
    $used = ptTemplate(['name' => "Used Template {$tag}", 'installs_count' => 3]);
    $draft = ptTemplate(['name' => "Draft Template {$tag}", 'status' => 'draft']);
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'pt-'.uniqid().'.test']);
    SiteTemplate::create(['site_id' => $site->id, 'template_id' => $used->id, 'name' => $used->name, 'source' => 'catalog', 'applied_at' => now()]);

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->set('q', $tag)
        ->assertSee("Used Template {$tag}")->assertSeeText('1 site using')
        ->set('status', 'draft')
        ->assertViewHas('catalog', fn ($page) => collect($page->items())->pluck('id')->all() === [$draft->id]);

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('open', $used->id)
        ->assertSee($site->name);
});

test('publish toggles store visibility but never exposes a private upload', function () {
    $admin = ptSuper();
    $t = ptTemplate(['status' => 'draft']);
    $private = ptTemplate(['status' => 'private', 'source' => 'upload']);

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('togglePublished', $t->id)
        ->call('togglePublished', $private->id);

    expect($t->fresh()->status)->toBe('published')
        ->and($private->fresh()->status)->toBe('private');

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)->call('togglePublished', $t->id);
    expect($t->fresh()->status)->toBe('draft');
});

test('edit saves price, category and description', function () {
    $admin = ptSuper();
    $t = ptTemplate();

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('startEdit', $t->id)
        ->set('edit.price', '19.50')->set('edit.category', 'Trades')->set('edit.short_description', 'For builders')
        ->call('saveEdit')->assertHasNoErrors();

    $t->refresh();
    expect($t->price_cents)->toBe(1950)->and($t->category)->toBe('Trades')->and($t->short_description)->toBe('For builders');
});

test('the review queue approves or sends back with a reason', function () {
    $admin = ptSuper();
    $a = ptTemplate(['status' => 'in_review', 'source' => 'custom']);
    $b = ptTemplate(['status' => 'in_review', 'source' => 'custom']);

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->set('tab', 'review')
        ->call('approve', $a->id)
        ->call('startReject', $b->id)
        ->call('reject')->assertHasErrors('rejectReason')
        ->set('rejectReason', 'Hero image is missing.')
        ->call('reject')->assertHasNoErrors();

    expect($a->fresh()->status)->toBe('published')
        ->and($b->fresh()->status)->toBe('rejected')
        ->and($b->fresh()->rejection_reason)->toBe('Hero image is missing.');
});

test('uploads from every account are listed and a failed one can be deleted with its files', function () {
    $admin = ptSuper();
    $client = User::factory()->create(['name' => 'Upload Client Qy']);
    $root = sys_get_temp_dir().'/pt-root-'.uniqid();
    config(['templates.uploads.path' => $root]);
    $upload = TemplateUpload::create([
        'user_id' => $client->id, 'key' => 'u-'.Str::lower(Str::random(10)), 'name' => 'Client Design Qy',
        'status' => TemplateUpload::FAILED, 'error' => 'The build failed: missing module',
    ]);
    File::ensureDirectoryExists(TemplatePaths::uploadsRoot().'/'.$upload->key.'/app');

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->set('tab', 'uploads')
        ->assertSee('Client Design Qy')->assertSee('Upload Client Qy')->assertSee('missing module')
        ->call('deleteUpload', $upload->id);

    expect(TemplateUpload::find($upload->id))->toBeNull()
        ->and(File::isDirectory(TemplatePaths::uploadsRoot().'/'.$upload->key))->toBeFalse();
    File::deleteDirectory($root);
});

test('a non-super cannot mount the component directly', function () {
    Livewire::actingAs(User::factory()->create())->test(PlatformTemplatesPage::class)->assertForbidden();
});

test('the editor saves name, description, tags and the features a template turns on', function () {
    $admin = ptSuper();
    $t = ptTemplate();

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('startEdit', $t->id)
        ->set('edit.name', 'Renamed Template')
        ->set('edit.description', 'A long description of what is inside.')
        ->set('edit.tags', 'church, events, church')
        ->set('edit.features', ['bookings', 'donations'])
        ->call('saveEdit')->assertHasNoErrors();

    $t->refresh();
    expect($t->name)->toBe('Renamed Template')
        ->and($t->description)->toBe('A long description of what is inside.')
        ->and($t->tags)->toBe(['church', 'events'])
        ->and($t->required_features)->toBe(['bookings', 'donations']);

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('startEdit', $t->id)->set('edit.name', '')->set('edit.features', ['not-a-feature'])
        ->call('saveEdit')->assertHasErrors(['edit.name', 'edit.features.0']);
});

test('an admin upload is queued for the store and lands as a draft by Olux Studio', function () {
    Queue::fake();
    $admin = ptSuper();

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('openUpload')
        ->set('appZip', UploadedFile::fake()->createWithContent('shop.zip', 'PK'))
        ->set('uploadName', 'Shop Starter')
        ->call('uploadTemplate')->assertHasNoErrors()
        ->assertSet('tab', 'uploads');

    $upload = TemplateUpload::where('user_id', $admin->id)->latest()->firstOrFail();
    expect($upload->for_store)->toBeTrue()->and($upload->name)->toBe('Shop Starter');
    Queue::assertPushed(ProcessTemplateUpload::class);
    File::delete($upload->zipPath());
});
