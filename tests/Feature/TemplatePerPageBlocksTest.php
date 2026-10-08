<?php

use App\Models\Component;
use App\Models\Site;
use App\Models\User;
use App\Services\TemplateScaffolder;

// Blocks whose content belongs to each page (a page hero reading its copy by
// route — flagged perPage by the template's `// @olux-per-page`, or given
// different text per page) get one component per page; everything else stays
// one shared record per site.

function perPageDefs(array $urls): array
{
    return array_map(fn ($u) => ['url' => $u, 'name' => ucwords(trim(str_replace(['/', '-'], ' ', $u))) ?: 'Home', 'blocks' => [
        ['name' => 'Page Hero Content', 'perPage' => true, 'nodes' => []],
        ['name' => 'Donate Cta', 'nodes' => [['label' => 'Heading', 'type' => 'text', 'value' => 'Give generously']]],
    ]], $urls);
}

it('gives per-page blocks their own copy on every page and keeps the rest shared', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'pp-'.uniqid().'.test']);
    app(TemplateScaffolder::class)->applyPages($site, perPageDefs(['/about', '/prayer', '/bible-study']));

    $heroes = Component::where('site_id', $site->id)->where('name', 'Page Hero Content')->get();
    expect($heroes)->toHaveCount(3)
        ->and($heroes->every(fn ($c) => $c->pages()->count() === 1))->toBeTrue()
        ->and(Component::where('site_id', $site->id)->where('name', 'Donate Cta')->count())->toBe(1);

    // Different template text per page counts as per-page too (no flag needed).
    expect(TemplateScaffolder::perPageBlocks([
        ['url' => '/a', 'blocks' => [['name' => 'Intro', 'nodes' => [['label' => 'Heading', 'value' => 'A']]]]],
        ['url' => '/b', 'blocks' => [['name' => 'Intro', 'nodes' => [['label' => 'Heading', 'value' => 'B']]]]],
    ]))->toHaveKey('Intro');
});

it('splits a per-page block older installs shared — the page it was edited for keeps it', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'pp-'.uniqid().'.test']);
    $urls = ['/about', '/bible-study', '/prayer', '/mens-ministry'];
    // An older install: one hero record shared by every page, edited on Bible Study.
    $shared = Component::create(['site_id' => $site->id, 'name' => 'Page Hero Content', 'author' => 'Olux', 'source' => 'app']);
    $shared->nodes()->create(['label' => 'Eyebrow', 'type' => 'text', 'value' => 'Bible study', 'parent' => '0', 'order' => 0]);
    $shared->nodes()->create(['label' => 'Heading', 'type' => 'text', 'value' => 'Go deeper in the Word', 'parent' => '0', 'order' => 1]);
    foreach ($urls as $i => $u) {
        $page = $site->pages()->create(['name' => ucwords(trim(str_replace(['/', '-'], ' ', $u))), 'url' => $u, 'keywords' => '', 'is_published' => true]);
        $page->components()->attach($shared->id, ['order' => 0]);
    }

    app(TemplateScaffolder::class)->applyPages($site, perPageDefs($urls), topUp: true);

    $heroFor = fn ($u) => $site->pages()->where('url', $u)->first()->components()->where('components.name', 'Page Hero Content')->get();
    foreach ($urls as $u) {
        expect($heroFor($u))->toHaveCount(1);
    }
    expect($heroFor('/bible-study')->first()->id)->toBe($shared->id)            // the edit stays where it was made
        ->and($shared->fresh()->pages()->count())->toBe(1)
        ->and($heroFor('/about')->first()->id)->not->toBe($shared->id)
        ->and($heroFor('/about')->first()->nodes()->count())->toBe(0);          // fresh copy → the template's own copy for /about
});

it('publishes pages a template update adds, only on sites that already publish', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'pp-'.uniqid().'.test']);
    $old = $site->pages()->create(['name' => 'About', 'url' => '/about', 'keywords' => '', 'is_published' => true]);
    $new = $site->pages()->create(['name' => "Men's Ministry", 'url' => '/mens-ministry', 'keywords' => '', 'is_published' => true]);
    $installer = app(App\Services\TemplateInstaller::class);

    // Never published anything → nothing goes out.
    expect($installer->publishNewPages($site))->toBe(0)
        ->and($new->fresh()->page_json_path)->toBeNull();

    app(App\Services\SiteConnect\PageJsonPublisher::class)->publish($old);
    $publishedAt = $old->fresh()->page_json_version;
    expect($installer->publishNewPages($site))->toBe(1)
        ->and($new->fresh()->page_json_path)->not->toBeNull()
        ->and($old->fresh()->page_json_version)->toBe($publishedAt);   // existing pages untouched
});

it('updates block text the owner never edited when the template changes it, and keeps edited text', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'pp-'.uniqid().'.test']);
    $def = fn (array $nodes) => [['url' => '/about', 'name' => 'About', 'blocks' => [['name' => 'Ministry Hub', 'nodes' => $nodes]]]];
    app(TemplateScaffolder::class)->applyPages($site, $def([
        ['label' => 'Text', 'type' => 'text', 'value' => 'Meet the leaders'],
        ['label' => 'Text B', 'type' => 'text', 'value' => 'Get involved'],
    ]));
    $hub = Component::where('site_id', $site->id)->where('name', 'Ministry Hub')->first();
    // the owner edits Text B later
    $edited = $hub->nodes()->where('label', 'Text B')->first();
    $this->travel(5)->minutes();
    $edited->update(['value' => 'Join us']);

    // the redesigned block names its fields differently
    app(TemplateScaffolder::class)->applyPages($site, $def([
        ['label' => 'Text', 'type' => 'text', 'value' => 'At a glance'],
        ['label' => 'Text B', 'type' => 'text', 'value' => 'Ministry leaders'],
        ['label' => 'Text C', 'type' => 'text', 'value' => 'Upcoming events'],
    ]), topUp: true);

    $values = $hub->nodes()->pluck('value', 'label')->all();
    expect($values['Text'])->toBe('At a glance')        // never edited → follows the template
        ->and($values['Text B'])->toBe('Join us')        // edited → kept
        ->and($values['Text C'])->toBe('Upcoming events'); // new field added
});

it('drops auto-named fields a redesigned block no longer has, unless someone edited them', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'pp-'.uniqid().'.test']);
    $def = fn (array $nodes) => [['url' => '/about', 'name' => 'About', 'blocks' => [['name' => 'Ministry Hub', 'nodes' => $nodes]]]];
    app(TemplateScaffolder::class)->applyPages($site, $def([
        ['label' => 'Text', 'type' => 'text', 'value' => 'About the ministry'],
        ['label' => 'Text B', 'type' => 'text', 'value' => 'Meet the leaders'],
        ['label' => 'Text C', 'type' => 'text', 'value' => 'Get involved'],
    ]));
    $hub = Component::where('site_id', $site->id)->where('name', 'Ministry Hub')->first();
    $hub->nodes()->create(['label' => 'Glance 5 Label', 'type' => 'text', 'value' => 'Added by the owner', 'parent' => '0', 'order' => 9]);
    $this->travel(5)->minutes();
    $hub->nodes()->where('label', 'Text C')->first()->update(['value' => 'Edited by the owner']);

    app(TemplateScaffolder::class)->applyPages($site, $def([
        ['label' => 'Leaders Title', 'type' => 'text', 'value' => 'Ministry leaders'],
        ['label' => 'Text', 'type' => 'text', 'value' => 'About the ministry'],
    ]), topUp: true);

    expect($hub->nodes()->pluck('label')->sort()->values()->all())
        ->toBe(['Glance 5 Label', 'Leaders Title', 'Text', 'Text C']);   // Text B gone; edited Text C and owner rows kept
});
