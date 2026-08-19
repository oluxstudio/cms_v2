<?php

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\Form;
use App\Models\Node;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteConnect\PageJsonGenerator;

/**
 * Builds a small but complete page — a hero component (with a nested CTA node),
 * a services collection, a published post, and a contact form — then asserts the
 * generated page.json matches the versioned contract exactly.
 */
function connectFixture(): Page
{
    $user = User::factory()->create();
    $site = Site::factory()->create([
        'user_id' => $user->id,
        'theme' => ['font' => 'Inter', 'accent' => '#7c3aed'],
    ]);
    $page = Page::factory()->create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/']);

    // Hero component: heading + image + nested cta {label, href}.
    $hero = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'test', 'source' => 'app']);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Heading', 'type' => 'text', 'value' => 'Look your best', 'order' => 0]);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Image', 'type' => 'image', 'value' => 'https://cdn.test/hero.webp', 'order' => 1]);
    $cta = Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Cta', 'type' => 'text', 'value' => '', 'order' => 2]);
    Node::create(['component_id' => $hero->id, 'parent' => $cta->id, 'label' => 'Label', 'type' => 'text', 'value' => 'Book now', 'order' => 0]);
    Node::create(['component_id' => $hero->id, 'parent' => $cta->id, 'label' => 'Href', 'type' => 'url', 'value' => '/book', 'order' => 1]);
    $page->components()->attach($hero->id, ['order' => 0]);

    // Services collection with two published items.
    $services = Collection::create(['site_id' => $site->id, 'name' => 'Services', 'type' => 'grid', 'is_public' => true]);
    CollectionItem::create(['collection_id' => $services->id, 'site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'Cut & Finish', 'price' => '£38']]);
    CollectionItem::create(['collection_id' => $services->id, 'site_id' => $site->id, 'status' => 'draft', 'data' => ['title' => 'Draft service', 'price' => '£0']]);
    $page->collections()->attach($services->id, ['order' => 1]);

    // A published post + a contact form.
    Post::create(['site_id' => $site->id, 'user_id' => $user->id, 'title' => 'Summer tips', 'slug' => 'summer-tips-'.uniqid(), 'excerpt' => 'ex', 'body' => '<p>hi</p>', 'status' => 'published', 'published_at' => now()]);
    Form::create(['site_id' => $site->id, 'name' => 'contact-'.uniqid(), 'title' => 'Contact', 'is_active' => true, 'fields' => [
        ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
    ]]);

    return $page->fresh();
}

test('page.json carries the schema version, site theme and nav', function () {
    $doc = app(PageJsonGenerator::class)->generate(connectFixture());

    expect($doc['schemaVersion'])->toBe(2)
        ->and($doc['siteData']['theme']['fonts']['body'])->toBe('Inter')
        ->and($doc['siteData']['theme']['colors']['primary'])->toBe('#7c3aed')
        ->and($doc['siteData']['nav'])->toContain(['label' => 'Home', 'href' => '/'])
        ->and($doc['pageData']['slug'])->toBe('index')
        ->and($doc['pageData']['name'])->toBe('Home');
});

test('a component flattens its node tree into fields incl. a nested cta', function () {
    $doc = app(PageJsonGenerator::class)->generate(connectFixture());

    $hero = $doc['componentData'][0];
    expect($hero['key'])->toBe('hero')
        ->and($hero['position'])->toBe(0)
        ->and($hero['fields']['heading'])->toBe('Look your best')
        ->and($hero['fields']['image'])->toBe(['src' => 'https://cdn.test/hero.webp', 'alt' => ''])
        ->and($hero['fields']['cta'])->toBe(['label' => 'Book now', 'href' => '/book'])
        ->and(strlen($hero['id']))->toBe(26); // stable ULID present
});

test('a collection emits schema + only published items', function () {
    $doc = app(PageJsonGenerator::class)->generate(connectFixture());

    $services = $doc['collectionData'][0];
    expect($services['key'])->toBe('services')
        ->and($services['items'])->toHaveCount(1)
        ->and($services['items'][0]['title'])->toBe('Cut & Finish')
        ->and($services['schema'])->toContain('title')->toContain('price');
});

test('posts and forms are included with a submit url', function () {
    $doc = app(PageJsonGenerator::class)->generate(connectFixture());

    expect($doc['postData'])->toHaveCount(1)
        ->and($doc['postData'][0]['title'])->toBe('Summer tips')
        ->and($doc['formData'])->toHaveCount(1)
        ->and($doc['formData'][0]['submitUrl'])->toContain('/form/')
        ->and($doc['formData'][0]['fields'][0]['name'])->toBe('email');
});

test('the document uses the v2 top-level shape with bookingData', function () {
    $doc = app(PageJsonGenerator::class)->generate(connectFixture());

    expect($doc)->toHaveKeys(['schemaVersion', 'siteData', 'pageData', 'componentData', 'collectionData', 'formData', 'bookingData', 'postData'])
        ->and($doc['siteData'])->toHaveKeys(['name', 'domain', 'logo', 'icon', 'theme', 'nav', 'version'])
        ->and($doc['pageData'])->toHaveKeys(['name', 'url', 'slug', 'createdAt', 'meta', 'styles'])
        // No bookings feature on this site → empty services, null availability.
        ->and($doc['bookingData']['services'])->toBe([])
        ->and($doc['bookingData']['availability'])->toBeNull();
});

test('positions increase across content types', function () {
    $doc = app(PageJsonGenerator::class)->generate(connectFixture());

    expect($doc['componentData'][0]['position'])->toBe(0)
        ->and($doc['collectionData'][0]['position'])->toBe(1);
});

test('page.json never leaks delivery internals, notification emails or drafts', function () {
    $page = connectFixture();
    $site = $page->site;

    // Operator-only data that must never reach the public document.
    Form::where('site_id', $site->id)->first()?->update([
        'delivery' => ['recipient' => 'owner-secret@example.com', 'external_action' => null,
            'success_message' => 'Thanks!'],
    ]);
    $col = Collection::where('site_id', $site->id)->first();
    CollectionItem::create(['collection_id' => $col->id, 'site_id' => $site->id,
        'status' => 'draft', 'data' => ['name' => 'UNPUBLISHED-DRAFT-ITEM']]);

    $json = json_encode(app(PageJsonGenerator::class)->generate($page->fresh()));

    expect($json)->not->toContain('owner-secret@example.com')
        ->not->toContain('UNPUBLISHED-DRAFT-ITEM')
        ->not->toContain('"delivery"')
        ->toContain('Thanks!'); // the public success message IS part of the contract
});
