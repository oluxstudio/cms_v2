<?php

use App\Livewire\SitePropertiesPage;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\ContentVersion;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteConnect\PageJsonGenerator;
use App\Support\SiteTokens;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;

// Site Names are unique across sites: keep each test's sites out of the next run.
uses(DatabaseTransactions::class);

/** A graceway-like site: profile entry, contact rows, header phone, a policy paragraph, a leadership list. */
function plSite(): array
{
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'pl-'.uniqid().'.test']);
    $col = fn (string $slug, array $rows) => tap(Collection::create(['site_id' => $site->id, 'name' => Str::headline($slug), 'slug' => $slug, 'type' => 'list', 'is_public' => true, 'fields' => []]),
        fn ($c) => collect($rows)->each(fn ($d, $i) => CollectionItem::create(['collection_id' => $c->id, 'site_id' => $site->id, 'status' => 'published', 'position' => $i, 'data' => $d])));
    $profile = $col('site-profile', [['name' => 'Grace Church', 'email' => 'hello@grace.org', 'phone' => '+44 20 1111 2222', 'address' => '1 High St', 'city' => 'Leeds']]);
    $contact = $col('contact-info', [
        ['label' => 'Phone Number', 'value' => '+44 20 1111 2222', 'href' => 'tel:+442011112222'],
        ['label' => 'Email Address', 'value' => 'hello@grace.org', 'href' => 'mailto:hello@grace.org'],
    ]);
    $legal = $col('legal-sections', [['heading' => 'Contact', 'body' => 'Write to hello@grace.org.'], ['heading' => 'Data', 'body' => 'We keep data safe.']]);
    $team = $col('leadership', [['name' => 'Ruth', 'email' => 'ruth@grace.org'], ['name' => 'Peter', 'email' => 'peter@grace.org']]);
    $skills = $col('profile-skills', [['name' => 'Teaching'], ['name' => 'Worship']]);
    $header = Component::create(['site_id' => $site->id, 'name' => 'Site Header', 'author' => 'api', 'source' => 'api']);
    $phone = Node::create(['component_id' => $header->id, 'parent' => '0', 'label' => 'Phone', 'type' => 'text', 'value' => '+44 20 1111 2222', 'order' => 0]);
    $phoneLink = Node::create(['component_id' => $header->id, 'parent' => '0', 'label' => 'Phone Link', 'type' => 'text', 'value' => 'tel:+442011112222', 'order' => 1]);

    return compact('owner', 'site', 'profile', 'contact', 'legal', 'team', 'skills', 'phone', 'phoneLink');
}

test('empty properties are filled from the template content, with where they came from', function () {
    $x = plSite();

    Livewire::actingAs($x['owner'])->test(SitePropertiesPage::class, ['site' => $x['site']])
        ->assertSet('values.site_name', 'Grace Church')
        ->assertSet('values.email', 'hello@grace.org')
        ->assertSet('values.address_street', '1 High St')
        ->assertSet('values.address_town', 'Leeds')
        ->assertSet('rows.phones.0.value', '+44 20 1111 2222')
        ->assertSet('filledFrom.email', 'Contact Info')
        ->assertSee('Filled from Site Profile');
});

test('saving writes changes to every linked place, keeping links in step and free text untouched', function () {
    $x = plSite();
    $page = Livewire::actingAs($x['owner'])->test(SitePropertiesPage::class, ['site' => $x['site']]);
    $page->call('save'); // first save keeps the filled values (nothing changes in the content)
    expect($x['contact']->items()->get()->pluck('data.value')->all())->toBe(['+44 20 1111 2222', 'hello@grace.org']);

    $page->set('values.email', 'office@grace.org')->set('rows.phones.0.value', '+44 113 555 0000')->set('values.site_name', 'Grace Church Leeds')
        ->call('save')->assertHasNoErrors()->assertDispatched('toast', fn ($n, $p) => str_contains($p['message'], 'Also updated'));

    $rows = $x['contact']->items()->get()->pluck('data')->all();
    expect($rows[0])->toEqualCanonicalizing(['label' => 'Phone Number', 'value' => '+44 113 555 0000', 'href' => 'tel:+441135550000'])
        ->and($rows[1])->toEqualCanonicalizing(['label' => 'Email Address', 'value' => 'office@grace.org', 'href' => 'mailto:office@grace.org'])
        ->and($x['profile']->items()->first()->data)->toMatchArray(['name' => 'Grace Church Leeds', 'email' => 'office@grace.org', 'phone' => '+44 113 555 0000'])
        ->and($x['phone']->fresh()->value)->toBe('+44 113 555 0000')
        ->and($x['phoneLink']->fresh()->value)->toBe('tel:+441135550000')
        // never touched: free text, other people's details, lists that merely say "profile"
        ->and($x['legal']->items()->first()->data['body'])->toBe('Write to hello@grace.org.')
        ->and($x['team']->items()->get()->pluck('data.email')->all())->toBe(['ruth@grace.org', 'peter@grace.org'])
        ->and($x['skills']->items()->get()->pluck('data.name')->all())->toBe(['Teaching', 'Worship']);

    // Each changed place got a checkpoint first.
    expect(ContentVersion::where('site_id', $x['site']->id)->where('subject_id', $x['contact']->id)->exists())->toBeTrue();
});

test('a property with no place in the content is added: contact rows, and a site profile entry', function () {
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'pl-'.uniqid().'.test']);

    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->set('values.site_name', 'Bloom Studio')->set('values.email', 'hi@bloom.test')->set('rows.phones', [['label' => 'Main', 'value' => '+44 7700 900123']])
        ->set('values.address_town', 'York')
        ->call('save')->assertHasNoErrors();

    $contact = Collection::where('site_id', $site->id)->where('slug', 'contact-info')->firstOrFail();
    expect($contact->items()->get()->pluck('data')->all())->toEqualCanonicalizing([
        ['label' => 'Email Address', 'value' => 'hi@bloom.test', 'href' => 'mailto:hi@bloom.test'],
        ['label' => 'Phone Number', 'value' => '+44 7700 900123', 'href' => 'tel:+447700900123'],
    ]);
    $profile = Collection::where('site_id', $site->id)->where('slug', 'site-profile')->firstOrFail();
    expect($profile->items()->first()->data)->toMatchArray(['name' => 'Bloom Studio', 'address_town' => 'York']);

    // Clearing a property never blanks the content.
    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site->fresh()])
        ->set('values.address_town', '')->call('save');
    expect($profile->items()->first()->fresh()->data['address_town'])->toBe('York');
});

test('tokens resolve to the current properties and custom variables wherever content is delivered', function () {
    $x = plSite();
    $site = $x['site'];
    Livewire::actingAs($x['owner'])->test(SitePropertiesPage::class, ['site' => $site])
        ->set('values.hours_monday', '09:00-17:00')->set('values.hours_tuesday', '09:00-17:00')->set('values.hours_wednesday', '09:00-17:00')
        ->set('values.hours_thursday', '09:00-17:00')->set('values.hours_friday', '09:00-17:00')->set('values.hours_saturday', '10:00-14:00')->set('values.hours_sunday', 'Closed')
        ->set('variables', [['key' => 'Opening Offer', 'type' => 'text', 'value' => '10% off in June']])
        ->call('save')->assertHasNoErrors()
        ->assertSee('{{opening_offer}}')->assertSee('{{phone}}');

    $map = SiteTokens::map($site->fresh());
    expect($map['phone'])->toBe('+44 20 1111 2222')
        ->and($map['hours'])->toBe('Mon–Fri 09:00–17:00 · Sat 10:00–14:00 · Sun Closed')
        ->and($map['opening_offer'])->toBe('10% off in June')
        ->and($map['address'])->toContain('1 High St, Leeds');

    // A block field and a collection entry using tokens.
    $page = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $cta = Component::create(['site_id' => $site->id, 'name' => 'Call To Action', 'author' => 'api', 'source' => 'api']);
    Node::create(['component_id' => $cta->id, 'parent' => '0', 'label' => 'Text', 'type' => 'text', 'value' => 'Call {{ phone }} — {{opening_offer}} {{unknown}}', 'order' => 0]);
    $page->components()->attach($cta->id, ['order' => 0]);
    $x['legal']->items()->first()->update(['data' => ['heading' => 'Hours', 'body' => 'We are open {{hours}}.']]);

    $content = json_encode($this->getJson('/api/sites/'.$site->name.'/content')->json(), JSON_UNESCAPED_UNICODE);
    expect($content)->toContain('Call +44 20 1111 2222 — 10% off in June {{unknown}}')
        ->and($content)->toContain('We are open Mon–Fri 09:00–17:00 · Sat 10:00–14:00 · Sun Closed.');

    $cols = json_encode($this->getJson('/api/sites/'.$site->name.'/collections')->json());
    expect($cols)->toContain('We are open Mon')->not->toContain('{{hours}}');

    $json = json_encode(app(PageJsonGenerator::class)->generate($page->fresh()));
    expect($json)->toContain('Call +44 20 1111 2222')->not->toContain('{{ phone }}');

    // The editor keeps the raw token.
    expect(Node::where('component_id', $cta->id)->value('value'))->toContain('{{ phone }}');
});

test('logo, favicon and opening hours are linked into the content too', function () {
    $x = plSite();
    $header = Component::where('site_id', $x['site']->id)->where('name', 'Site Header')->first();
    $logo = Node::create(['component_id' => $header->id, 'parent' => '0', 'label' => 'Logo', 'type' => 'image', 'value' => '/assets/logo.png', 'order' => 2]);
    $x['contact']->items()->create(['site_id' => $x['site']->id, 'status' => 'published', 'position' => 3, 'data' => ['label' => 'Opening Hours', 'value' => 'Mon – Fri · 9 – 5']]);

    Livewire::actingAs($x['owner'])->test(SitePropertiesPage::class, ['site' => $x['site']])
        ->assertSet('values.logo', '/assets/logo.png')                 // filled from the header
        ->assertSet('values.hours_monday', '')                          // hours are never read back
        ->set('values.logo', '/storage/media/new-logo.png')
        ->set('values.hours_monday', '08:00-16:00')->set('values.hours_tuesday', '08:00-16:00')
        ->call('save')->assertHasNoErrors();

    expect($logo->fresh()->value)->toBe('/storage/media/new-logo.png')
        ->and($x['contact']->items()->get()->firstWhere('data.label', 'Opening Hours')->data['value'])->toBe('Mon–Tue 08:00–16:00');
});
