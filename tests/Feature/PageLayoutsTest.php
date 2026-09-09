<?php

use App\Livewire\PageComponent;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Support\TemplateLayouts;
use Livewire\Livewire;

function layoutSite(bool $templated = true): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'lay-'.uniqid(),
        'domain' => 'lay-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    if ($templated) {
        $site->forceFill(['template' => 'v2hairco'])->save();
    }

    return [$owner, $site];
}

test('the layout catalog lists the applied template\'s pages with their blocks', function () {
    [$owner, $site] = layoutSite();

    $layouts = TemplateLayouts::for($site);
    expect($layouts)->toHaveKey('about')
        ->and($layouts['about']['blocks'])->toContain('About')->toContain('Stats')
        ->and($layouts)->toHaveKey('services');

    [$o2, $bare] = layoutSite(templated: false);
    expect(TemplateLayouts::for($bare))->toBe([]);
});

test('creating a page from a layout scaffolds its sections, reusing same-name components', function () {
    [$owner, $site] = layoutSite();
    // Pre-existing site component with the same name must be REUSED, not duplicated.
    $existingAbout = $site->contentComponents()->create(['name' => 'About', 'author' => 't', 'source' => 'app']);

    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('openCreate')
        ->set('form.name', 'Our Story')
        ->set('form.url', '/our-story')
        ->set('form.keywords', 'story')
        ->set('layout', 'about')
        ->call('save');

    $page = Page::where('site_id', $site->id)->where('url', '/our-story')->first();
    expect($page)->not->toBeNull()
        ->and($page->name)->toBe('Our Story');

    $names = $page->components()->orderBy('page_component.order')->pluck('components.name');
    expect($names->all())->toContain('About')->toContain('Stats')->toContain('Team')
        // The pre-existing About was reused — still exactly one on the site.
        ->and($site->contentComponents()->where('name', 'About')->count())->toBe(1)
        ->and($page->components()->where('components.id', $existingAbout->id)->exists())->toBeTrue();

    // Newly created components carry the layout's default content.
    $stats = $site->contentComponents()->where('name', 'Stats')->first();
    expect($stats->nodes()->count())->toBeGreaterThan(0);
});

test('blank layout still creates a bare page and duplicate URLs are rejected', function () {
    [$owner, $site] = layoutSite();

    $lw = Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('openCreate')
        ->set('form.name', 'Plain')
        ->set('form.url', '/plain')
        ->set('form.keywords', '')
        ->call('save');

    $page = Page::where('site_id', $site->id)->where('url', '/plain')->first();
    expect($page)->not->toBeNull()
        ->and($page->components()->count())->toBe(0);

    // Same URL again → validation error, no scaffold.
    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('openCreate')
        ->set('form.name', 'Plain Two')
        ->set('form.url', '/plain')
        ->set('form.keywords', '')
        ->set('layout', 'about')
        ->call('save')
        ->assertHasErrors(['form.url']);
    expect(Page::where('site_id', $site->id)->where('url', '/plain')->count())->toBe(1);
});
