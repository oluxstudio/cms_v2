<?php

use App\Livewire\TemplateGalleryPage;
use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateEntitlement;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

function galleryTemplate(array $extra = []): Template
{
    return Template::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'user_id' => User::factory()->create()->id,
        'name' => 'Gallery '.uniqid(), 'slug' => 'gal-'.uniqid(),
        'description' => 'A lovely gallery test design', 'category' => 'Business',
        'status' => 'published', 'price_cents' => 0, 'currency' => 'gbp',
        'source' => 'creator', 'published_at' => now(),
    ], $extra));
}

test('guests can browse the gallery, open details, and are asked to sign up', function () {
    publishBuiltinTemplate('verita');
    $tpl = galleryTemplate();
    $draft = galleryTemplate(['status' => 'draft', 'name' => 'Hidden '.uniqid()]);

    $this->get('/designs')->assertOk()->assertSee('Get started free');
    $this->get('/designs?q=Verita')->assertOk()->assertSee('Verita');
    // Pagination may push a fresh zero-install template off page 1 — search finds it.
    $this->get('/designs?q='.urlencode($tpl->name))->assertOk()->assertSee($tpl->name)->assertDontSee($draft->name);

    Livewire::test(TemplateGalleryPage::class)
        ->call('openDetail', 'catalog:'.$tpl->slug)
        ->assertSee('Sign up to use this')
        ->assertDontSee('Save to one of your sites');
});

test('a logged-in owner saves a design to their site (catalog with entitlement, curated without)', function () {
    $verita = publishBuiltinTemplate('verita');
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'gal-'.uniqid(), 'domain' => 'gal-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $tpl = galleryTemplate();

    $c = Livewire::actingAs($owner)->test(TemplateGalleryPage::class);
    $c->call('openDetail', 'catalog:'.$tpl->slug)->call('saveToSite', $site->id);
    expect($site->installedTemplates()->where('template_id', $tpl->id)->exists())->toBeTrue()
        ->and(TemplateEntitlement::where('user_id', $owner->id)->where('template_id', $tpl->id)->exists())->toBeTrue()
        ->and($tpl->fresh()->installs_count)->toBe(1);

    // Duplicate save no-ops.
    $c->call('saveToSite', $site->id);
    expect($site->installedTemplates()->where('template_id', $tpl->id)->count())->toBe(1);

    // Curated save: builtin row, no entitlement involved.
    $c->call('openDetail', 'catalog:'.$verita->slug)->call('saveToSite', $site->id);
    expect($site->installedTemplates()->where('builtin_key', 'verita')->value('source'))->toBe('catalog');
});

test('the gallery lists exactly the published + public templates from Admin › Templates, with their tagline', function () {
    $public = galleryTemplate(['visibility' => 'public', 'short_description' => 'Bold pages for busy salons '.uniqid()]);
    $private = galleryTemplate(['visibility' => 'private', 'name' => 'Private '.uniqid()]);
    $hidden = galleryTemplate(['status' => 'hidden', 'name' => 'Hidden '.uniqid()]);

    $html = $this->get('/designs?q='.urlencode($public->name))->assertOk()->getContent();
    expect($html)->toContain($public->short_description)   // tagline as the card's short line
        ->toContain('aspect-square');                       // square thumbnails

    $names = collect(Livewire::test(TemplateGalleryPage::class)->instance()->pool())->pluck('name');
    expect($names)->toContain($public->name)
        ->not->toContain($private->name)   // private and unpublished never reach the public page
        ->not->toContain($hidden->name);

    // A first-party design without a published catalog row isn't public (not even by direct link).
    Template::where('builtin_key', 'tekstack')->update(['status' => 'hidden']);
    $this->get('/designs/curated-tekstack')->assertNotFound();
});
