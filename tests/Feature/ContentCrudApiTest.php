<?php

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

function crudSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'crud-'.uniqid(),
        'domain' => 'crud.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $raw = Str::random(64);
    ApiToken::create(['user_id' => $owner->id, 'name' => 'crud', 'token' => hash('sha256', $raw)]);

    return [$owner, $site, ['Authorization' => 'Bearer '.$raw]];
}

test('posts CRUD API creates, publishes, updates and deletes with a bearer token', function () {
    [$owner, $site, $auth] = crudSite();
    $base = "/api/sites/{$site->name}/posts";

    // Unauthenticated writes are rejected.
    $this->postJson($base, ['title' => 'Nope'])->assertStatus(401);

    $created = $this->postJson($base, [
        'title' => 'Hello World', 'excerpt' => 'The first one', 'body' => '<p>Body</p>', 'status' => 'published',
    ], $auth)->assertCreated()->json('post');
    expect($created['slug'])->toBe('hello-world')
        ->and($created['status'])->toBe('published')
        ->and($created['published_at'])->not->toBeNull();

    // Published post appears on the public feed.
    $this->getJson($base)->assertOk()->assertJsonPath('posts.0.slug', 'hello-world');

    $this->patchJson("$base/hello-world", ['excerpt' => 'Edited', 'status' => 'draft'], $auth)
        ->assertOk()->assertJsonPath('post.excerpt', 'Edited')->assertJsonPath('post.status', 'draft');

    $this->deleteJson("$base/hello-world", [], $auth)->assertOk();
    expect(Post::where('site_id', $site->id)->count())->toBe(0);
});

test('pages CRUD API manages pages and their attributes', function () {
    [$owner, $site, $auth] = crudSite();
    $base = "/api/sites/{$site->name}/pages";

    $created = $this->postJson($base, [
        'name' => 'About', 'url' => '/about', 'keywords' => 'about, team',
        'attributes' => ['hero_title' => 'Who we are', 'theme' => 'dark'],
    ], $auth)->assertCreated()->json('page');
    expect($created['attributes'])->toMatchArray(['hero_title' => 'Who we are', 'theme' => 'dark']);

    // Duplicate url rejected.
    $this->postJson($base, ['name' => 'About 2', 'url' => '/about'], $auth)->assertStatus(422);

    // Public read includes the page and its attributes.
    $this->getJson($base)->assertOk()->assertJsonPath('pages.0.url', '/about');

    // Update: rename, unpublish, forget one attribute (null) and set another.
    $updated = $this->patchJson("$base/{$created['id']}", [
        'name' => 'About us', 'is_published' => false,
        'attributes' => ['theme' => null, 'hero_sub' => 'Since 2020'],
    ], $auth)->assertOk()->json('page');
    expect($updated['is_published'])->toBeFalse()
        ->and($updated['attributes'])->toHaveKey('hero_sub')
        ->and($updated['attributes'])->not->toHaveKey('theme');

    $this->deleteJson("$base/{$created['id']}", [], $auth)->assertOk();
    expect(Page::where('site_id', $site->id)->count())->toBe(0);
});

test('assets CRUD API registers, edits and deletes media by url', function () {
    [$owner, $site, $auth] = crudSite();
    $base = "/api/sites/{$site->name}/media";

    $created = $this->postJson($base, [
        'url' => 'https://cdn.example.com/photos/team.jpg', 'name' => 'Team photo', 'alt' => 'The team',
    ], $auth)->assertCreated()->json('asset');
    expect($created['type'])->toBe('image')->and($created['name'])->toBe('Team photo');

    // Public library lists it.
    $this->getJson($base)->assertOk()->assertJsonPath('data.0.name', 'Team photo');

    $this->patchJson("$base/{$created['id']}", ['name' => 'Team 2026', 'alt' => 'All of us'], $auth)
        ->assertOk()->assertJsonPath('asset.name', 'Team 2026');

    $this->deleteJson("$base/{$created['id']}", [], $auth)->assertOk();
    expect(Media::where('site_id', $site->id)->count())->toBe(0);
});

test('collections CRUD API manages collections and nested items, hiding private ones from public reads', function () {
    [$owner, $site, $auth] = crudSite();
    $base = "/api/sites/{$site->name}/collections";

    $created = $this->postJson($base, [
        'name' => 'Testimonials', 'type' => 'list', 'description' => 'What clients say',
        'fields' => [['key' => 'quote', 'label' => 'Quote', 'type' => 'textarea']],
    ], $auth)->assertCreated()->json('collection');

    $item = $this->postJson("$base/{$created['id']}/items", [
        'data' => ['quote' => 'Great work!'],
    ], $auth)->assertCreated()->json('item');
    $this->postJson("$base/{$created['id']}/items", [
        'data' => ['quote' => 'Hidden draft'], 'status' => 'pending',
    ], $auth)->assertCreated();

    // Public read: collection visible, only the published item.
    $public = $this->getJson("$base/{$created['id']}")->assertOk()->json('collection');
    expect($public['items'])->toHaveCount(1)
        ->and($public['items'][0]['data']['quote'])->toBe('Great work!');

    // Item update + delete.
    $this->patchJson("$base/{$created['id']}/items/{$item['id']}", ['data' => ['quote' => 'Amazing!']], $auth)
        ->assertOk()->assertJsonPath('item.data.quote', 'Amazing!');
    $this->deleteJson("$base/{$created['id']}/items/{$item['id']}", [], $auth)->assertOk();

    // Private collections disappear from public reads.
    $this->patchJson("$base/{$created['id']}", ['is_public' => false], $auth)->assertOk();
    $this->getJson("$base/{$created['id']}")->assertStatus(404);
    expect($this->getJson($base)->json('collections'))->toHaveCount(0);

    $this->deleteJson("$base/{$created['id']}", [], $auth)->assertOk();
    expect($site->collections()->count())->toBe(0);
});

test('forms CRUD API creates a form that can be fetched and submitted, then updates and deletes it', function () {
    [$owner, $site, $auth] = crudSite();
    $base = "/api/sites/{$site->name}/forms";

    $created = $this->postJson($base, [
        'name' => 'Project Enquiry', 'title' => 'Start a project',
        'fields' => [
            ['key' => 'full name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'type' => 'email', 'required' => true],
            ['key' => 'budget', 'type' => 'select', 'options' => ['< £1k', '£1k–£5k', '£5k+']],
        ],
    ], $auth)->assertCreated()->json('form');
    expect($created['name'])->toBe('project-enquiry')
        ->and($created['fields'][0]['key'])->toBe('full_name')
        ->and($created['submit_url'])->toContain('/form/project-enquiry');

    // Public: form directory + per-form schema both serve it.
    $this->getJson($base)->assertOk()->assertJsonPath('forms.0.name', 'project-enquiry');
    $this->getJson("/api/sites/{$site->name}/form/project-enquiry")->assertOk()
        ->assertJsonPath('title', 'Start a project');

    // Public submission against the API-created schema: bad data 422, good data 201.
    $submit = "/api/sites/{$site->name}/form/project-enquiry";
    $this->postJson($submit, ['full_name' => 'Jo'])->assertStatus(422);
    $this->postJson($submit, ['full_name' => 'Jo Bloggs', 'email' => 'jo@example.com', 'budget' => '£1k–£5k'])
        ->assertCreated();
    expect(Form::where('site_id', $site->id)->first()->responses()->count())->toBe(1);

    // Deactivate via PATCH → submissions are refused.
    $this->patchJson("$base/project-enquiry", ['is_active' => false], $auth)
        ->assertOk()->assertJsonPath('form.is_active', false);
    $this->postJson($submit, ['full_name' => 'Late', 'email' => 'late@example.com'])->assertStatus(403);

    // Delete removes the form and its responses.
    $this->deleteJson("$base/project-enquiry", [], $auth)->assertOk();
    expect(Form::where('site_id', $site->id)->count())->toBe(0);
});

test('a member without manage permissions cannot write through the content CRUD APIs', function () {
    [$owner, $site, $auth] = crudSite();
    $outsider = User::factory()->create();
    $badRaw = Str::random(64);
    ApiToken::create(['user_id' => $outsider->id, 'name' => 'x', 'token' => hash('sha256', $badRaw)]);
    $bad = ['Authorization' => 'Bearer '.$badRaw];

    $this->postJson("/api/sites/{$site->name}/posts", ['title' => 'No'], $bad)->assertStatus(403);
    $this->postJson("/api/sites/{$site->name}/pages", ['name' => 'No', 'url' => '/no'], $bad)->assertStatus(403);
    $this->postJson("/api/sites/{$site->name}/media", ['url' => 'https://x.test/a.png'], $bad)->assertStatus(403);
    $this->postJson("/api/sites/{$site->name}/collections", ['name' => 'No'], $bad)->assertStatus(403);
    $this->postJson("/api/sites/{$site->name}/forms", ['name' => 'No', 'fields' => [['key' => 'x']]], $bad)->assertStatus(403);
});
