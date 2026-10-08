<?php

use App\Livewire\ConnectReviewPage;
use App\Livewire\SitePropertiesPage;
use App\Models\AccountMember;
use App\Models\ContentVersion;
use App\Models\Media;
use App\Models\Page;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\Ai\SiteKnowledge;
use App\Services\SiteConnect\PageJsonGenerator;
use App\Services\SiteIcons;
use App\Support\SiteProperties;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

// Site Names are unique across sites: keep each test's sites out of the next run.
uses(DatabaseTransactions::class);

function propsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'props-'.uniqid(), 'domain' => 'props-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);

    return [$owner, $site];
}

test('the owner opens the properties page with every tab; a viewer without the permission cannot', function () {
    [$owner, $site] = propsSite();

    $res = $this->actingAs($owner)->get("/{$site->name}/properties")->assertOk();
    foreach (config('site-properties.tabs') as $label) {
        $res->assertSee($label);
    }

    $viewer = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $viewer->id,
        'role_id' => Role::forAccount($owner)->firstWhere('slug', 'viewer')->id, 'site_id' => $site->id]);
    $this->actingAs($viewer)->get("/{$site->name}/properties")->assertForbidden();
});

test('properties live in one page-less "Site Properties" component whose nodes follow the schema', function () {
    [, $site] = propsSite();

    $c = SiteProperties::component($site);
    expect($c->name)->toBe('Site Properties')
        ->and($c->pages()->count())->toBe(0)
        ->and($c->tags)->toContain('site:properties')
        ->and($c->nodes->pluck('label'))->toContain('Site Name', 'Tagline', 'Logo', 'Hours Monday', 'Vat Number', 'Title Pattern', 'Assistant Tone')
        ->and($c->nodes->firstWhere('label', 'Logo')->type)->toBe('image')
        ->and($c->nodes->firstWhere('label', 'Noindex While Draft')->type)->toBe('boolean')
        ->and($c->nodes->firstWhere('label', 'Title Pattern')->value)->toBe('{page} | {site}');

    // Idempotent, and a new schema field is topped up without touching values.
    $c->nodes->firstWhere('label', 'Tagline')->update(['value' => 'Kept']);
    config(['site-properties.fields.new_thing' => ['label' => 'New Thing', 'input' => 'text', 'tab' => 'brand']]);
    $again = SiteProperties::component($site->fresh());
    expect($again->id)->toBe($c->id)
        ->and($again->nodes->firstWhere('label', 'Tagline')->value)->toBe('Kept')
        ->and($again->nodes->pluck('label'))->toContain('New Thing');
});

test('saving writes fields, repeater rows and variables as nodes, with a checkpoint', function () {
    [$owner, $site] = propsSite();

    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->set('values.site_name', 'Grace Way Church')
        ->set('values.tagline', 'A church family')
        ->set('values.email', 'hello@graceway.test')
        ->set('values.hours_monday', '09:00-12:00, 13:00-17:00')
        ->set('values.vat_number', 'GB 123 4567 89')
        ->set('values.noindex', '1')
        ->call('addRow', 'phones')->set('rows.phones.0', ['label' => 'Office', 'value' => '+44 20 7946 0000'])
        ->call('addRow', 'phones')->set('rows.phones.1', ['label' => 'Mobile', 'value' => '07700 900123'])
        ->call('addRow', 'phones') // blank → dropped
        ->call('addRow', 'closures')->set('rows.closures.0', ['date' => '2026-12-25', 'hours' => 'Closed', 'note' => 'Christmas'])
        ->call('addVariable', 'image')->set('variables.0', ['key' => 'Hero Image', 'type' => 'image', 'value' => '/storage/hero.jpg'])
        ->set('currency', 'eur')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $nodes = SiteProperties::find($site)->nodes()->pluck('value', 'label');
    expect($nodes['Site Name'])->toBe('Grace Way Church')
        ->and($nodes['Phone 1 Label'])->toBe('Office')
        ->and($nodes['Phone 2 Value'])->toBe('07700 900123')
        ->and($nodes->has('Phone 3 Value'))->toBeFalse()
        ->and($nodes['Closure 1 Date'])->toBe('2026-12-25')
        ->and($nodes['Hero Image'])->toBe('/storage/hero.jpg')
        ->and($nodes['Noindex'])->toBe('1')
        ->and($site->fresh()->currency)->toBe('eur')
        ->and($site->fresh()->getAttr('business_name'))->toBe('Grace Way Church')
        ->and(ContentVersion::where('subject_id', SiteProperties::find($site)->id)->exists())->toBeTrue();

    // Reopening shows the same data.
    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site->fresh()])
        ->assertSet('values.tagline', 'A church family')
        ->assertSet('rows.phones.1.label', 'Mobile')
        ->assertSet('variables.0.key', 'Hero Image');
});

test('an edit made to the component nodes (as the Edit page does) shows on the properties page', function () {
    [$owner, $site] = propsSite();
    $c = SiteProperties::component($site);
    $c->nodes->firstWhere('label', 'Tagline')->update(['value' => 'Edited in edit mode']);
    $c->nodes()->create(['parent' => '0', 'label' => 'Opening Line', 'type' => 'text', 'value' => 'Welcome', 'order' => 999]);

    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->assertSet('values.tagline', 'Edited in edit mode')
        ->assertSet('variables.0.key', 'Opening Line');
});

test('invalid values are rejected per type and the page jumps to the tab with the error', function () {
    [$owner, $site] = propsSite();

    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->set('values.hours_tuesday', 'mornings')
        ->set('values.vat_number', 'not a vat')
        ->set('values.facebook', 'facebook.com/me')
        ->set('values.ga4_id', 'UA-123')
        ->set('rows.phones', [['label' => 'Office', 'value' => 'call me']])
        ->set('variables', [['key' => 'Site Name', 'type' => 'text', 'value' => 'x'], ['key' => 'dup', 'type' => 'text', 'value' => 'a'], ['key' => 'dup', 'type' => 'text', 'value' => 'b']])
        ->call('save')
        ->assertHasErrors(['values.hours_tuesday', 'values.vat_number', 'values.facebook', 'values.ga4_id', 'rows.phones.0.value', 'variables.0.key', 'variables.1.key'])
        ->assertDispatched('properties-error');

    expect(SiteProperties::find($site)->nodes()->where('label', 'Phone 1 Value')->exists())->toBeFalse();
});

test('the first version\'s attributes are folded into the component once', function () {
    [, $site] = propsSite();
    $site->setAttr('site.display_name', 'Legacy Name');
    $site->setAttr('site.phones', json_encode([['label' => 'Office', 'value' => '0123']]));
    $site->setAttr('site.variables', json_encode([['key' => 'motto', 'type' => 'text', 'value' => 'Hi']]));

    $p = SiteProperties::get($site, SiteProperties::component($site));
    expect($p['values']['site_name'])->toBe('Legacy Name')
        ->and($p['rows']['phones'][0]['value'])->toBe('0123')
        ->and($p['variables'][0])->toBe(['key' => 'motto', 'type' => 'text', 'value' => 'Hi'])
        ->and($site->fresh()->getAttr('site.display_name'))->toBeNull();
});

test('the content API serves grouped properties and templates get the schema.org business', function () {
    [, $site] = propsSite();
    SiteProperties::save($site, ['values' => [
        'site_name' => 'Grace Way', 'logo' => '/storage/logo.png', 'business_type' => 'Church',
        'address_street' => '1 High St', 'address_town' => 'Blackburn', 'address_postcode' => 'BB1 1AA',
        'hours_sunday' => '10:00-12:00, 18:00-19:30', 'hours_monday' => 'Closed', 'facebook' => 'https://facebook.com/gw',
        'latitude' => '53.75', 'longitude' => '-2.48',
    ], 'rows' => ['phones' => [['label' => 'Office', 'value' => '0123']]], 'variables' => [['key' => 'motto', 'type' => 'text', 'value' => 'Welcome home']]]);

    $this->getJson("/api/sites/{$site->name}/content")
        ->assertOk()
        ->assertJsonPath('site.properties.name', 'Grace Way')
        ->assertJsonPath('site.properties.logo', url('/storage/logo.png'))
        ->assertJsonPath('site.properties.business.address.town', 'Blackburn')
        ->assertJsonPath('site.properties.hours.sunday.1', ['18:00', '19:30'])
        ->assertJsonPath('site.properties.hours.monday', [])
        ->assertJsonPath('site.properties.social.facebook', 'https://facebook.com/gw')
        ->assertJsonPath('site.properties.variables.motto', 'Welcome home')
        ->assertJsonPath('site.properties.seo.noindex', true); // not live + noindex while draft

    $ld = SiteProperties::schemaOrg($site->fresh(), null, 'https://gw.test');
    expect($ld['@type'])->toBe('Church')
        ->and($ld['address']['postalCode'])->toBe('BB1 1AA')
        ->and($ld['geo']['latitude'])->toBe(53.75)
        ->and(collect($ld['openingHoursSpecification'])->where('dayOfWeek', 'Sunday')->count())->toBe(2)
        ->and($ld['sameAs'])->toContain('https://facebook.com/gw')
        ->and($ld['telephone'])->toBe('0123');

    expect($site->fresh()->brandLogo())->toBe(url('/storage/logo.png')); // absolute — it goes into emails
});

test('the Edit page opens the same Site Properties component, and a save there shows on the Properties page', function () {
    [$owner, $site] = propsSite();

    $lw = Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site])
        ->assertSee('Site properties')
        ->call('openSiteProperties')
        ->assertSet('edit.siteProperties', true)
        ->assertSee('Contact &amp; social', false);

    $nodes = $lw->get('edit.nodes');
    $idx = collect($nodes)->search(fn ($n) => $n['label'] === 'Tagline');
    $lw->set("edit.nodes.$idx.value", 'Set in edit mode')->call('saveComponent');

    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site->fresh()])
        ->assertSet('values.tagline', 'Set in edit mode');

    // …and the other way round: a Properties-page save is what the Edit page loads.
    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site->fresh()])
        ->set('values.tagline', 'Set on the properties page')->call('save')->assertHasNoErrors();
    $again = Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site->fresh()])->call('openSiteProperties');
    expect(collect($again->get('edit.nodes'))->firstWhere('label', 'Tagline')['value'])->toBe('Set on the properties page');

    // Built-in fields can't be removed from the Edit page; custom ones can.
    $i = collect($again->get('edit.nodes'))->search(fn ($n) => $n['label'] === 'Site Name');
    $count = count($again->get('edit.nodes'));
    $again->call('removeNode', $i);
    expect(count($again->get('edit.nodes')))->toBe($count);
});

test('?properties=1 opens Site Properties; editing it needs the properties permission', function () {
    [$owner, $site] = propsSite();

    $this->actingAs($owner)->get("/{$site->name}/connect?properties=1")->assertOk()->assertSee('name, logo, contact, hours');

    // A member who may edit components but not site properties.
    $editor = User::factory()->create();
    $role = Role::forAccount($owner)->firstWhere('slug', 'content-editor') ?? Role::forAccount($owner)->firstWhere('slug', 'editor');
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $editor->id, 'role_id' => $role->id, 'site_id' => $site->id]);
    expect($site->allows($editor, 'components.manage'))->toBeTrue()
        ->and($site->allows($editor, 'properties.manage'))->toBeFalse();

    Livewire::actingAs($editor)->test(ConnectReviewPage::class, ['site' => $site])
        ->assertDontSee('name, logo, contact, hours')
        ->call('openSiteProperties')
        ->assertForbidden();
});

test('a square icon becomes favicon, Apple and app icons in Assets; replaced on the next one; remote URLs refused', function () {
    Storage::fake('public');
    [, $site] = propsSite();
    $png = function (int $w, int $h) use ($site) {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 90));
        ob_start();
        imagepng($img);
        $path = 'media/'.$site->name.'/src-'.uniqid().'.png';
        Storage::disk('public')->put($path, ob_get_clean());

        return Storage::url($path);
    };

    $icons = app(SiteIcons::class);
    expect($icons->generate($site, $png(600, 520)))->toBeTrue();
    $set = json_decode($site->getAttr('site.icons'), true);
    expect(array_keys($set))->toBe([32, 48, 180, 192, 512]);
    $first = Str::after($set[512], '/storage/');
    [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($first));
    expect([$w, $h])->toBe([512, 512])
        ->and(Media::where('site_id', $site->id)->where('name', 'favicon-32.png')->count())->toBe(1);

    expect($icons->generate($site, $png(400, 400)))->toBeTrue();
    Storage::disk('public')->assertMissing($first);
    expect(Media::where('site_id', $site->id)->where('name', 'favicon-32.png')->count())->toBe(1);

    expect($icons->generate($site, 'https://evil.example/x.png'))->toBeFalse()
        ->and($icons->generate($site, $png(100, 100)))->toBeFalse()   // too small
        ->and($icons->generate($site, ''))->toBeNull()
        ->and($site->fresh()->getAttr('site.icons'))->toBeNull();
});

test('templates get Site Properties on every page, in page.json, and the assistant knows the business', function () {
    [$owner, $site] = propsSite();
    $page = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    SiteProperties::save($site, ['values' => ['site_name' => 'Grace Way', 'tagline' => 'Welcome home', 'address_town' => 'Blackburn', 'hours_sunday' => '10:00-12:00']]);

    $wire = $this->getJson("/api/sites/{$site->name}/page?url=/")->assertOk()->json('page.wireframe')
        ?? $this->getJson("/api/sites/{$site->name}/content")->json('pages.0.wireframe');
    $block = collect($wire)->first(fn ($b) => str_ends_with($b['type'], ':site-properties'));
    expect($block)->not->toBeNull()
        ->and(collect($block['nodes'])->firstWhere('label', 'Tagline')['value'])->toBe('Welcome home');

    $doc = app(PageJsonGenerator::class)->generate($page->fresh());
    expect($doc['siteData']['properties']['tagline'])->toBe('Welcome home');

    expect(SiteKnowledge::profileText($site->fresh()))
        ->toContain('About Grace Way.')->toContain('Blackburn')->toContain('Sunday: 10:00–12:00');
});

test('a Site Name must be unique across sites (case and spacing ignored), on both the Properties and Edit pages', function () {
    [, $first] = propsSite();
    $taken = 'Unique Name '.uniqid();
    SiteProperties::save($first, ['values' => ['site_name' => $taken]]);

    [$owner, $site] = propsSite();
    expect(SiteProperties::nameTaken('  '.strtoupper($taken).' ', $site))->toBeTrue()
        ->and(SiteProperties::nameTaken($taken, $first))->toBeFalse();    // a site keeps its own name

    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->set('values.site_name', mb_strtolower($taken))->call('save')
        ->assertHasErrors(['values.site_name']);
    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->set('values.site_name', $taken.' Two')->call('save')
        ->assertHasNoErrors();

    $lw = Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site->fresh()])->call('openSiteProperties');
    $i = collect($lw->get('edit.nodes'))->search(fn ($n) => $n['label'] === 'Site Name');
    $lw->set("edit.nodes.$i.value", $taken)->call('saveComponent')->assertDispatched('toast', level: 'error');
    expect(SiteProperties::value($site, 'site_name'))->toBe($taken.' Two');
});

test('a site with no Site Name set is known by its address, so that name is taken too', function () {
    [, $other] = propsSite();   // name like props-abc123, no Site Name stored
    [, $site] = propsSite();

    expect(SiteProperties::nameTaken(Str::headline($other->name), $site))->toBeTrue();
    SiteProperties::save($other, ['values' => ['site_name' => 'Renamed '.uniqid()]]);
    expect(SiteProperties::nameTaken(Str::headline($other->name), $site))->toBeFalse();
});

test('a site that already shares its name with another can still save its other properties', function () {
    $shared = 'Shared '.uniqid();
    [, $a] = propsSite();
    [$owner, $b] = propsSite();
    SiteProperties::save($a, ['values' => ['site_name' => $shared]]);
    SiteProperties::save($b, ['values' => ['site_name' => $shared]]);   // e.g. from before names had to be unique

    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $b])
        ->set('values.tagline', 'Still saves')->call('save')
        ->assertHasNoErrors();
    expect(SiteProperties::value($b, 'tagline'))->toBe('Still saves');
});

test('logo text and subtext prefill from the Site Profile, save back to it, and reach the site', function () {
    [$owner, $site] = propsSite();
    $profile = App\Models\Collection::create(['site_id' => $site->id, 'name' => 'Site Profile', 'slug' => '', 'type' => 'grid', 'fields' => [
        ['key' => 'name', 'type' => 'text'], ['key' => 'logoText', 'type' => 'textarea'], ['key' => 'logoSub', 'type' => 'text'],
    ]]);
    $row = $profile->items()->create(['site_id' => $site->id, 'status' => 'published',
        'data' => ['name' => 'Grace Way', 'logoText' => "CAC\nMount Zion", 'logoSub' => 'Blackburn.']]);

    $page = Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->assertSet('values.logo_text', "CAC\nMount Zion")
        ->assertSet('values.logo_subtext', 'Blackburn.')
        ->assertSee('Logo Text')->assertSee('Logo Subtext');

    $page->set('values.logo_text', "Grace Way\nChurch")->set('values.logo_subtext', 'Since 1992')->call('save')->assertHasNoErrors();

    expect($row->fresh()->data['logoText'])->toBe("Grace Way\nChurch")
        ->and($row->fresh()->data['logoSub'])->toBe('Since 1992')
        ->and(SiteProperties::payload($site->fresh())["logo_text"] ?? null)->toBe("Grace Way\nChurch");
});

test('clearing an optional linked property (Logo Subtext) clears the Site Profile copy; required ones never blank it', function () {
    [$owner, $site] = propsSite();
    $profile = App\Models\Collection::create(['site_id' => $site->id, 'name' => 'Site Profile', 'slug' => '', 'type' => 'grid', 'fields' => [
        ['key' => 'logoSub', 'type' => 'text'], ['key' => 'tagline', 'type' => 'text'],
    ]]);
    $row = $profile->items()->create(['site_id' => $site->id, 'status' => 'published', 'data' => ['logoSub' => 'Blackburn.', 'tagline' => 'Come as you are']]);

    // the page shows the prefilled values; the owner empties both and saves
    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->assertSet('values.logo_subtext', 'Blackburn.')
        ->set('values.logo_subtext', '')
        ->set('values.tagline', '')
        ->call('save')->assertHasNoErrors();

    expect($row->fresh()->data['logoSub'])->toBe('')            // optional → cleared on the site
        ->and($row->fresh()->data['tagline'])->toBe('Come as you are'); // not clearable → template content kept

    // reopening shows it empty (nothing left to prefill from)
    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])->assertSet('values.logo_subtext', '');
});
