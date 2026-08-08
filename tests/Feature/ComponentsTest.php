<?php

use App\Livewire\ComponentsPage;
use App\Models\AccountMember;
use App\Models\ApiToken;
use App\Models\Component;
use App\Models\Page;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

function componentSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'comp-'.uniqid(),
        'domain' => 'comp.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $page = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);

    return [$owner, $site, $page];
}

test('admin builds a standalone component with nodes and attaches it to a page', function () {
    [$owner, $site, $page] = componentSite();
    $collection = $site->collections()->create(['name' => 'Team', 'type' => 'list']);

    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->call('open', 0)
        ->set('cName', 'Hero banner')
        ->set('cDescription', 'Top of the home page')
        ->set('nodes', [
            ['label' => 'Heading', 'type' => 'text', 'value' => 'Welcome!', 'description' => ''],
            ['label' => 'Show CTA', 'type' => 'boolean', 'value' => '1', 'description' => ''],
            ['label' => 'People', 'type' => 'collection', 'value' => (string) $collection->id, 'description' => ''],
        ])
        ->set('pageIds', [(string) $page->id])
        ->call('save');

    $component = $site->contentComponents()->first();
    expect($component->name)->toBe('Hero banner')
        ->and($component->nodes()->pluck('label')->all())->toBe(['Heading', 'Show CTA', 'People'])
        ->and($component->pages()->pluck('pages.id')->all())->toBe([$page->id])
        ->and($component->collections()->pluck('id')->all())->toBe([$collection->id]);
});

test('the site content API includes site attributes, page attributes and components with all nodes', function () {
    [, $site, $page] = componentSite();
    $site->setAttr('brand_color', '#e38704');
    $page->setAttr('hero_style', 'wide');

    $component = Component::create(['site_id' => $site->id, 'name' => 'Hero banner', 'author' => 'Test']);
    $component->nodes()->create(['label' => 'Heading', 'type' => 'text', 'value' => 'Welcome!', 'parent' => 0, 'order' => 0]);
    $component->nodes()->create(['label' => 'Image', 'type' => 'image', 'value' => '/images/x.jpg', 'parent' => 0, 'order' => 1]);
    $component->pages()->attach($page->id, ['order' => 0]);

    $this->getJson("/api/sites/{$site->name}/content")
        ->assertOk()
        ->assertJsonPath('site.attributes.brand_color', '#e38704')
        ->assertJsonPath('pages.0.attributes.hero_style', 'wide')
        ->assertJsonPath('pages.0.components.0.name', 'Hero banner')
        ->assertJsonPath('pages.0.components.0.nodes.0.label', 'Heading')
        ->assertJsonPath('pages.0.components.0.nodes.1.type', 'image');
});

test('the components CRUD API creates, reads, updates and deletes with a bearer token', function () {
    [$owner, $site, $page] = componentSite();
    $raw = Str::random(64);
    $token = ApiToken::create(['user_id' => $owner->id, 'name' => 'test', 'token' => hash('sha256', $raw)]);
    $auth = ['Authorization' => 'Bearer '.$raw];

    // Create (with nodes + page attachment).
    $res = $this->postJson("/api/sites/{$site->name}/components", [
        'name' => 'Footer CTA',
        'nodes' => [
            ['label' => 'Text', 'type' => 'text', 'value' => 'Get in touch'],
            ['label' => 'Link', 'type' => 'url', 'value' => '/contact'],
        ],
        'page_ids' => [$page->id],
    ], $auth)->assertStatus(201);
    $id = $res->json('component.id');
    expect($res->json('component.nodes'))->toHaveCount(2)
        ->and($res->json('component.pages.0.id'))->toBe($page->id);

    // Public read.
    $this->getJson("/api/sites/{$site->name}/components")
        ->assertOk()->assertJsonPath('components.0.name', 'Footer CTA');
    $this->getJson("/api/sites/{$site->name}/components/{$id}")
        ->assertOk()->assertJsonPath('component.nodes.1.label', 'Link');

    // Update: rename + replace nodes + detach pages.
    $this->patchJson("/api/sites/{$site->name}/components/{$id}", [
        'name' => 'Footer CTA v2',
        'nodes' => [['label' => 'Text', 'type' => 'text', 'value' => 'Say hello']],
        'page_ids' => [],
    ], $auth)->assertOk()
        ->assertJsonPath('component.name', 'Footer CTA v2')
        ->assertJsonPath('component.nodes.0.value', 'Say hello');
    expect(Component::find($id)->pages()->count())->toBe(0);

    // Unauthenticated writes are refused.
    $this->postJson("/api/sites/{$site->name}/components", ['name' => 'Nope'])->assertStatus(401);

    // Delete.
    $this->deleteJson("/api/sites/{$site->name}/components/{$id}", [], $auth)->assertOk();
    expect(Component::find($id))->toBeNull();
});

test('members without components.manage cannot edit components', function () {
    [$owner, $site] = componentSite();
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id]);

    Livewire::actingAs($member)->test(ComponentsPage::class, ['site' => $site])
        ->call('open', 0)->assertStatus(403);
    $this->actingAs($member)->get("/{$site->name}/components")->assertOk(); // view allowed
});

test('components carry tags and the pages picker filters by them', function () {
    [$owner, $site, $page] = componentSite();

    // Tags saved from the Components editor (comma-separated box).
    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->call('open', 0)
        ->set('cName', 'Hero banner')
        ->set('cTags', 'hero, marketing , hero')
        ->set('nodes', [['label' => 'Heading', 'type' => 'text', 'value' => 'Hi', 'description' => '']])
        ->call('save');
    $hero = $site->contentComponents()->first();
    expect($hero->tags)->toBe(['hero', 'marketing']); // trimmed + deduped

    $footer = Component::create(['site_id' => $site->id, 'name' => 'Footer CTA', 'author' => 'T', 'tags' => ['footer']]);
    $footer->nodes()->create(['label' => 'Text', 'type' => 'text', 'value' => 'x', 'parent' => 0, 'order' => 0]);

    // Pages page picker: tag filter narrows the list, toggle attaches/detaches.
    $picker = Livewire::actingAs($owner)->test(\App\Livewire\PageComponent::class, ['site' => $site])
        ->call('openPicker', $page->id)
        ->set('pickerTag', 'hero');
    expect($picker->instance()->pickerComponents->pluck('name')->all())->toBe(['Hero banner']);

    $picker->call('toggleComponent', $hero->id);
    expect($page->components()->pluck('components.id')->all())->toBe([$hero->id]);

    $picker->call('toggleComponent', $hero->id); // untick = detach
    expect($page->components()->count())->toBe(0);

    // Tags flow through the public API payload too.
    $this->getJson("/api/sites/{$site->name}/components")
        ->assertOk()
        ->assertJsonPath('components.0.tags.0', 'footer')   // newest first
        ->assertJsonPath('components.1.tags.0', 'hero');
});

test('components record who created them and how, shown in the detail view', function () {
    [$owner, $site, $page] = componentSite();

    // Created via the APP interface → source=app, creator = the admin.
    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->call('open', 0)->set('cName', 'App made')
        ->set('nodes', [['label' => 'Heading', 'type' => 'text', 'value' => 'x', 'description' => '']])
        ->call('save');
    $appMade = $site->contentComponents()->firstWhere('name', 'App made');
    expect($appMade->source)->toBe('app')
        ->and($appMade->created_by)->toBe($owner->id);

    // Created via the API → source=api, creator = the token's user.
    $raw = Str::random(64);
    $token = ApiToken::create(['user_id' => $owner->id, 'name' => 't', 'token' => hash('sha256', $raw)]);
    $this->postJson("/api/sites/{$site->name}/components", [
        'name' => 'Api made',
        'nodes' => [['label' => 'Text', 'type' => 'text', 'value' => 'y']],
    ], ['Authorization' => 'Bearer '.$raw])->assertStatus(201)
        ->assertJsonPath('component.source', 'api')
        ->assertJsonPath('component.created_by', $owner->name);

    // The detail view renders every stored fact.
    $html = Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->call('view', $appMade->id)->html();
    expect($html)->toContain('#'.$appMade->id)
        ->toContain('App interface')
        ->toContain($owner->name)
        ->toContain('Created')
        ->toContain('Last modified');
});

test('image and url nodes render the reusable asset picker in the editor', function () {
    [$owner, $site] = componentSite();
    $site->media()->create(['name' => 'Hero shot', 'file_type' => 'image', 'url' => 'https://cdn.test/hero.jpg', 'size' => 100]);

    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->call('open', 0)
        ->set('nodes', [
            ['label' => 'Photo', 'type' => 'image', 'value' => '', 'description' => ''],
            ['label' => 'Link', 'type' => 'url', 'value' => '', 'description' => ''],
            ['label' => 'Title', 'type' => 'text', 'value' => '', 'description' => ''],
        ])
        ->assertSeeHtml('Pick from the asset library')
        ->assertSeeHtml('Image URL or pick from assets')
        ->assertSeeHtml('URL or pick any asset')
        ->assertSeeHtml('/api/sites/'.$site->name.'/media');
});
