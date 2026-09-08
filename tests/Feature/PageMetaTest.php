<?php

use App\Livewire\PageComponent;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function metaSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'meta-'.uniqid(),
        'domain' => 'meta-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $page = Page::create(['site_id' => $site->id, 'name' => 'About', 'url' => '/about', 'keywords' => 'salon,hair', 'is_published' => true]);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site, $page];
}

test('the detail drawer loads existing metadata and attributes', function () {
    [$owner, $site, $page] = metaSite();
    $page->setAttr('description', 'The best salon in Leeds');
    $page->setAttr('theme_accent', 'gold');
    $page->setAttr('custom_js', 'console.log(1)'); // managed — hidden from rows

    $lw = Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('show', $page->id);

    expect($lw->get('metaDescription'))->toBe('The best salon in Leeds')
        ->and($lw->get('metaKeywords'))->toBe('salon,hair')
        ->and(collect($lw->get('attrRows'))->pluck('key'))->toContain('theme_accent')->not->toContain('custom_js');
});

test('saveMeta writes description, keywords, og image and reconciles custom attributes', function () {
    [$owner, $site, $page] = metaSite();
    $page->setAttr('old_attr', 'goes away');
    $page->setAttr('custom_js', 'keep me');

    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('show', $page->id)
        ->set('metaDescription', 'Fresh cuts and colour, seven days a week.')
        ->set('metaKeywords', 'haircut, colour, leeds')
        ->set('ogImage', 'https://example.com/share.jpg')
        ->set('isPublished', false)
        ->set('attrRows', [['key' => 'hero_style', 'value' => 'wide']])
        ->call('saveMeta');

    $page->refresh();
    expect($page->getAttr('description'))->toBe('Fresh cuts and colour, seven days a week.')
        ->and($page->keywords)->toBe('haircut, colour, leeds')
        ->and($page->getAttr('og_image'))->toBe('https://example.com/share.jpg')
        ->and($page->is_published)->toBeFalse()
        ->and($page->getAttr('hero_style'))->toBe('wide')
        ->and($page->getAttr('old_attr'))->toBeNull()      // removed row → forgotten
        ->and($page->getAttr('custom_js'))->toBe('keep me'); // managed key untouched

    // Blanking the description forgets the attribute entirely.
    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('show', $page->id)
        ->set('metaDescription', '')
        ->call('saveMeta');
    expect($page->fresh()->getAttr('description'))->toBeNull();
});

test('the saved description reaches the public content API payload', function () {
    [$owner, $site, $page] = metaSite();
    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('show', $page->id)
        ->set('metaDescription', 'Visible to the renderer')
        ->call('saveMeta');

    $res = $this->getJson("/api/sites/{$site->name}/content")->assertOk()->json();
    $about = collect($res['pages'] ?? [])->firstWhere('url', '/about');
    expect($about['description'] ?? null)->toBe('Visible to the renderer');
});

test('invalid attribute keys are rejected and the layout list shows attached components in order', function () {
    [$owner, $site, $page] = metaSite();

    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('show', $page->id)
        ->set('attrRows', [['key' => 'bad key!', 'value' => 'x']])
        ->call('saveMeta')
        ->assertHasErrors(['attrRows.0.key']);

    $a = $site->contentComponents()->create(['name' => 'Hero', 'author' => 't', 'source' => 'app']);
    $b = $site->contentComponents()->create(['name' => 'Footer', 'author' => 't', 'source' => 'app']);
    $page->components()->attach($a->id, ['order' => 2]);
    $page->components()->attach($b->id, ['order' => 1]);

    Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->call('show', $page->id)
        ->assertSeeInOrder(['Footer', 'Hero']); // pivot order, not attach order
});
