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
    $tpl = galleryTemplate();
    $draft = galleryTemplate(['status' => 'draft', 'name' => 'Hidden '.uniqid()]);

    $this->get('/designs')->assertOk()->assertSee('Verita')->assertSee('Get started free');
    // Pagination may push a fresh zero-install template off page 1 — search finds it.
    $this->get('/designs?q='.urlencode($tpl->name))->assertOk()->assertSee($tpl->name)->assertDontSee($draft->name);

    Livewire::test(TemplateGalleryPage::class)
        ->call('openDetail', 'catalog:'.$tpl->slug)
        ->assertSee('Sign up to use this')
        ->assertDontSee('Save to one of your sites');
});

test('a logged-in owner saves a design to their site (catalog with entitlement, curated without)', function () {
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
    $c->call('openDetail', 'curated:verita')->call('saveToSite', $site->id);
    expect($site->installedTemplates()->where('builtin_key', 'verita')->value('source'))->toBe('builtin');
});
