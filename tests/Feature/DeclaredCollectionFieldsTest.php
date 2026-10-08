<?php

use App\Models\Collection;
use App\Models\Site;
use App\Services\CollectionSourceExtractor;
use App\Support\CollectionFieldShape;
use Illuminate\Support\Facades\File;

// A template declares a collection's field types with @olux-field lines in
// the collection's docblock; editors render (and validate) by those types.

function declaredFieldsApp(): string
{
    $root = storage_path('framework/testing/declared-fields-'.uniqid());
    File::ensureDirectoryExists("$root/app/composables");
    File::put("$root/app/composables/useData.ts", <<<'TS'
/** @olux-collection Projects
 * @olux-field images images
 * @olux-field title text required
 * @olux-field description textarea
 * @olux-field tech tags
 * @olux-field url url label="Live site"
 * @olux-field status select options=draft|live
 * @olux-field bogus wibble
 */
const projects = [
	{ title: 'One', description: 'A long description of the first project', tech: ['Nuxt', 'Vue'], images: ['/assets/a.png', '/assets/b.png'], url: '#' },
	{ title: 'Two', description: 'A long description of the second project', tech: ['Laravel'], images: ['/assets/c.png'], url: '#' },
]

/** @olux-collection Plain */
const plain = [
	{ name: 'Ann', bio: 'Undeclared fields keep their inferred types' },
]
TS);

    return $root;
}

it('reads declared field types, required flags, labels and options', function () {
    $root = declaredFieldsApp();
    $cols = collect(app(CollectionSourceExtractor::class)->fromSources($root))->keyBy('name');
    File::deleteDirectory($root);

    $f = collect($cols['Projects']['fields'])->keyBy('key');
    expect($f['images']['type'])->toBe('images')
        ->and($f['title']['type'])->toBe('text')->and($f['title']['required'])->toBeTrue()
        ->and($f['description']['type'])->toBe('textarea')->and($f['description']['required'])->toBeFalse()
        ->and($f['tech']['type'])->toBe('tags')
        ->and($f['url']['type'])->toBe('url')->and($f['url']['label'])->toBe('Live site')
        ->and($f['status']['type'])->toBe('select')->and($f['status']['options'])->toBe(['draft', 'live'])
        ->and($f['title']['declared'])->toBeTrue()
        ->and($f->has('bogus'))->toBeFalse()         // unknown types are ignored
        ->and($cols['Projects']['items'])->toHaveCount(2);

    // A collection without declarations still extracts with inferred types.
    expect(collect($cols['Plain']['fields'])->pluck('key')->all())->toBe(['name', 'bio'])
        ->and(collect($cols['Plain']['fields'])->every(fn ($x) => empty($x['declared'])))->toBeTrue();
});

it('shapes text stored under list fields into arrays', function () {
    $fields = [['key' => 'images', 'type' => 'images'], ['key' => 'tech', 'type' => 'tags'], ['key' => 'title', 'type' => 'text']];
    $data = CollectionFieldShape::shape(['images' => "/a.png\n/b.png\n", 'tech' => 'Nuxt, Vue,Tailwind', 'title' => 'Keep, as text'], $fields);

    expect($data['images'])->toBe(['/a.png', '/b.png'])
        ->and($data['tech'])->toBe(['Nuxt', 'Vue', 'Tailwind'])
        ->and($data['title'])->toBe('Keep, as text');
});

it('lists required fields that are empty', function () {
    $fields = [['key' => 'title', 'label' => 'Title', 'required' => true], ['key' => 'images', 'label' => 'Images', 'required' => true], ['key' => 'year', 'label' => 'Year']];

    expect(CollectionFieldShape::missingRequired(['title' => '  ', 'images' => [], 'year' => ''], $fields))->toBe(['Title', 'Images'])
        ->and(CollectionFieldShape::missingRequired(['title' => 'Ok', 'images' => ['/a.png']], $fields))->toBe([]);
});

it('reads a declared system source and fills it when an entry is created', function () {
    $root = storage_path('framework/testing/declared-auto-'.uniqid());
    File::ensureDirectoryExists("$root/app/composables");
    File::put("$root/app/composables/useStudies.ts", <<<'TS'
/** @olux-collection Studies
 * @olux-field title text required
 * @olux-field date date auto=created_at
 * @olux-field week text auto=not-a-source
 */
const studies = [
	{ title: 'Fruit That Lasts', date: '2026-09-10', week: 'Week 4' },
]
TS);
    $cols = collect(app(CollectionSourceExtractor::class)->fromSources($root))->keyBy('name');
    File::deleteDirectory($root);

    $f = collect($cols['Studies']['fields'])->keyBy('key');
    expect($f['date']['type'])->toBe('date')->and($f['date']['auto'])->toBe('created_at')
        ->and($f['week'])->not->toHaveKey('auto'); // unknown sources are ignored

    $site = Site::factory()->create();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Studies', 'type' => 'grid',
        'fields' => array_values($cols['Studies']['fields'])]);

    // A new entry with no date gets its creation date; an authored date is kept.
    $new = $col->items()->create(['site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'New study']]);
    $seed = $col->items()->create(['site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'Old', 'date' => '2026-09-10']]);
    expect($new->data['date'])->toStartWith(now()->format('Y-m-d'))
        ->and($seed->data['date'])->toBe('2026-09-10');
});

it('keeps a type\'s size suffix (textarea2/6/10/15), toggle and auto= sources', function () {
    $root = storage_path('framework/testing/declared-sizes-'.uniqid());
    File::ensureDirectoryExists("$root/app/composables");
    File::put("$root/app/composables/useStudies.ts", <<<'TS'
/** @olux-collection Bible Studies
 * @olux-field title text required
 * @olux-field summary textarea10
 * @olux-field takeaway textarea15
 * @olux-field memoryVerse textarea2 label="Memory verse"
 * @olux-field excerpt textarea6
 * @olux-field featured toggle
 * @olux-field date date auto=created_at
 */
const studies = [
	{ title: 'One', summary: 's', takeaway: 't', memoryVerse: 'm', excerpt: 'e', featured: true, date: '2026-01-01' },
]
TS);
    $cols = collect(app(CollectionSourceExtractor::class)->fromSources($root))->keyBy('name');
    File::deleteDirectory($root);

    $f = collect($cols['Bible Studies']['fields'])->keyBy('key');
    expect($f['summary']['type'])->toBe('textarea10')
        ->and($f['takeaway']['type'])->toBe('textarea15')
        ->and($f['memoryVerse']['type'])->toBe('textarea2')->and($f['memoryVerse']['label'])->toBe('Memory verse')
        ->and($f['excerpt']['type'])->toBe('textarea6')
        ->and($f['featured']['type'])->toBe('toggle')
        ->and($f['date']['type'])->toBe('date')->and($f['date']['auto'])->toBe('created_at');
});

it('reads hidden fields, datetime and typed, conditional sub-fields of a rows field', function () {
    $root = storage_path('framework/testing/declared-rows-'.uniqid());
    File::ensureDirectoryExists("$root/app/composables");
    File::put("$root/app/composables/useEvents.ts", <<<'TS'
/** @olux-collection Events
 * @olux-field id text hidden
 * @olux-field date datetime
 * @olux-field media rows
 * @olux-field media.type select options=image|video|audio
 * @olux-field media.img image label="Image / poster"
 * @olux-field media.src media label="Source" show=type:audio|video
 * @olux-field featured select options=yes|no
 */
const events = [
	{ id: 'a', date: '2026-10-18T19:00', title: 'One', featured: 'yes', media: [ { type: 'image', title: 'Pic', img: '/a.jpg' }, { type: 'video', title: 'Clip', img: '/p.jpg', src: '/v.mp4' } ] },
]
TS);
    $cols = collect(app(CollectionSourceExtractor::class)->fromSources($root))->keyBy('name');
    File::deleteDirectory($root);

    $f = collect($cols['Events']['fields'])->keyBy('key');
    expect($f['id']['hidden'])->toBeTrue()
        ->and($f['date']['type'])->toBe('datetime')
        ->and($f['featured']['options'])->toBe(['yes', 'no'])
        ->and($f['media']['type'])->toBe('rows');

    $sub = collect($f['media']['fields'])->keyBy('key');
    // declared sub-fields first, in order, then the inferred rest (title)
    expect(collect($f['media']['fields'])->pluck('key')->all())->toBe(['type', 'img', 'src', 'title'])
        ->and($sub['type']['type'])->toBe('select')->and($sub['type']['options'])->toBe(['image', 'video', 'audio'])
        ->and($sub['img']['type'])->toBe('image')->and($sub['img']['label'])->toBe('Image / poster')
        ->and($sub['src']['type'])->toBe('media')
        ->and($sub['src']['show'])->toBe(['field' => 'type', 'in' => ['audio', 'video']]);
});

it('reads a select whose choices come from another collection, and lists that collection\'s entries', function () {
    $root = storage_path('framework/testing/declared-from-'.uniqid());
    File::ensureDirectoryExists("$root/app/composables");
    File::put("$root/app/composables/useSermons.ts", <<<'TS'
/** @olux-collection Sermons
 * @olux-field seriesSlug select from=sermon-series:slug:name label="Series"
 */
const sermons = [
	{ title: 'One', seriesSlug: 'rooted' },
	{ title: 'Two', seriesSlug: 'rooted' },
	{ title: 'Three', seriesSlug: 'rooted' },
]
TS);
    $cols = collect(app(CollectionSourceExtractor::class)->fromSources($root))->keyBy('name');
    File::deleteDirectory($root);
    $f = collect($cols['Sermons']['fields'])->firstWhere('key', 'seriesSlug');
    expect($f['optionsFrom'])->toBe(['collection' => 'sermon-series', 'value' => 'slug', 'label' => 'name'])
        ->and($f)->not->toHaveKey('options');   // no stale guessed list

    $site = \App\Models\Site::factory()->create();
    $series = \App\Models\Collection::create(['site_id' => $site->id, 'name' => 'Sermon Series', 'slug' => '', 'type' => 'grid', 'fields' => []]);
    $series->items()->create(['site_id' => $site->id, 'status' => 'published', 'position' => 0, 'data' => ['slug' => 'rooted', 'name' => 'Rooted']]);
    $series->items()->create(['site_id' => $site->id, 'status' => 'published', 'position' => 1, 'data' => ['name' => 'Grace in the Psalms']]); // name only
    $series->items()->create(['site_id' => $site->id, 'status' => 'published', 'position' => 2, 'data' => ['name' => '']]);                    // blank: skipped

    expect(\App\Support\CollectionFieldOptions::for($site->id, $f))
        ->toBe(['rooted' => 'Rooted', 'grace-in-the-psalms' => 'Grace in the Psalms']);
});
