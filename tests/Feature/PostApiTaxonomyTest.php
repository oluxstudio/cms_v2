<?php

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function postSite(): array
{
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);

    return [$site, connectToken($site, ['posts.manage'])];
}

it('creates a post with category and tags via the client API and serves them publicly', function () {
    [$site, $key] = postSite();

    $created = $this->postJson('/api/site/posts', [
        'title' => 'Summer styling guide',
        'body' => '<p>Hello</p>',
        'category' => 'Hair care',
        'tags' => ['styling', 'summer'],
        'status' => 'published',
    ], ['Authorization' => 'Bearer '.$key])->assertCreated()->json('post');

    expect($created['category'])->toBe('Hair care')
        ->and($created['tags'])->toBe(['styling', 'summer']);

    // Public read (what the client site consumes) carries the taxonomy.
    $pub = $this->getJson('/api/sites/'.$site->name.'/posts')->assertOk()->json('posts.0');
    expect($pub['category'])->toBe('Hair care')->and($pub['tags'])->toBe(['styling', 'summer']);

    $detail = $this->getJson('/api/sites/'.$site->name.'/posts/'.$created['slug'])->assertOk()->json();
    expect($detail['category'])->toBe('Hair care')->and($detail['body'])->toBe('<p>Hello</p>');
});

it('accepts comma-separated tags and updates taxonomy', function () {
    [$site, $key] = postSite();

    $slug = $this->postJson('/api/site/posts', [
        'title' => 'Tag string post', 'tags' => 'one, two , three', 'status' => 'published',
    ], ['Authorization' => 'Bearer '.$key])->assertCreated()->json('post.slug');

    $updated = $this->patchJson('/api/site/posts/'.$slug, [
        'category' => 'News', 'tags' => 'four',
    ], ['Authorization' => 'Bearer '.$key])->assertOk()->json('post');

    expect($updated['tags'])->toBe(['four'])->and($updated['category'])->toBe('News');
});

it('filters the public list by category and tag', function () {
    [$site, $key] = postSite();
    $h = ['Authorization' => 'Bearer '.$key];
    $this->postJson('/api/site/posts', ['title' => 'A', 'category' => 'News', 'tags' => ['x'], 'status' => 'published'], $h);
    $this->postJson('/api/site/posts', ['title' => 'B', 'category' => 'Guides', 'tags' => ['y'], 'status' => 'published'], $h);

    $news = $this->getJson('/api/sites/'.$site->name.'/posts?category=news')->json('posts');
    expect($news)->toHaveCount(1)->and($news[0]['title'])->toBe('A');

    $tagged = $this->getJson('/api/sites/'.$site->name.'/posts?tag=y')->json('posts');
    expect($tagged)->toHaveCount(1)->and($tagged[0]['title'])->toBe('B');
});
