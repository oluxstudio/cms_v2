<?php

use App\Models\ApiToken;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

function commentSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'cm-'.uniqid(), 'domain' => 'example.com', 'owner' => $owner->name, 'description' => 't']);
    $post = Post::create(['site_id' => $site->id, 'user_id' => $owner->id, 'title' => 'Hello', 'slug' => 'hello-'.uniqid(), 'body' => '<p>hi</p>', 'status' => 'published', 'published_at' => now()]);

    return [$owner, $site, $post];
}

function postsToken(Site $site, User $owner): string
{
    $raw = Str::random(64);
    ApiToken::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'name' => 't',
        'token' => hash('sha256', $raw), 'token_preview' => substr($raw, 0, 6),
        'abilities' => ['posts.manage'], 'expires_at' => now()->addDay(),
    ]);

    return $raw;
}

test('a submitted comment is held for moderation and hidden from the public', function () {
    [$owner, $site, $post] = commentSite();

    $this->postJson("/api/sites/{$site->name}/posts/{$post->slug}/comments", [
        'author_name' => 'Jo', 'body' => 'Nice post!',
    ])->assertCreated();

    $c = Comment::where('post_id', $post->id)->first();
    expect($c->status)->toBe('pending');

    // Not in the public list, not in the post detail, count still 0.
    expect($this->getJson("/api/sites/{$site->name}/posts/{$post->slug}/comments")->json('comments'))->toHaveCount(0);
    $detail = $this->getJson("/api/sites/{$site->name}/posts/{$post->slug}");
    expect($detail->json('comments'))->toHaveCount(0)
        ->and($detail->json('comments_count'))->toBe(0);
});

test('approving a comment publishes it and bumps the cached count', function () {
    [$owner, $site, $post] = commentSite();
    $this->postJson("/api/sites/{$site->name}/posts/{$post->slug}/comments", ['author_name' => 'Jo', 'body' => 'Great!'])->assertCreated();
    $comment = Comment::where('post_id', $post->id)->first();
    $token = postsToken($site, $owner);

    $this->withToken($token)->patchJson("/api/sites/{$site->name}/comments/{$comment->id}", ['status' => 'approved'])
        ->assertOk();

    // Public list + post detail now show it; count is 1.
    expect($this->getJson("/api/sites/{$site->name}/posts/{$post->slug}/comments")->json('comments'))->toHaveCount(1);
    $detail = $this->getJson("/api/sites/{$site->name}/posts/{$post->slug}");
    expect($detail->json('comments.0.author_name'))->toBe('Jo')
        ->and($detail->json('comments_count'))->toBe(1);
    expect($post->fresh()->comments)->toBe(1);       // cached int column

    // Posts INDEX keeps comments as the integer count (non-breaking).
    $index = $this->getJson("/api/sites/{$site->name}/posts")->json('posts.0');
    expect($index['comments'])->toBe(1);
});

test('deleting an approved comment decrements the count', function () {
    [$owner, $site, $post] = commentSite();
    $comment = Comment::create(['post_id' => $post->id, 'site_id' => $site->id, 'author_name' => 'Jo', 'body' => 'x', 'status' => 'approved']);
    $post->increment('comments');
    $token = postsToken($site, $owner);

    $this->withToken($token)->deleteJson("/api/sites/{$site->name}/comments/{$comment->id}")->assertOk();

    expect($post->fresh()->comments)->toBe(0)
        ->and(Comment::find($comment->id))->toBeNull();
});

test('a foreign origin is rejected when submitting a comment', function () {
    [$owner, $site, $post] = commentSite();

    $this->withHeaders(['Origin' => 'https://evil.example.net'])
        ->postJson("/api/sites/{$site->name}/posts/{$post->slug}/comments", ['author_name' => 'Jo', 'body' => 'x'])
        ->assertForbidden();
});

test('moderation requires a posts.manage token', function () {
    [$owner, $site, $post] = commentSite();
    $comment = Comment::create(['post_id' => $post->id, 'site_id' => $site->id, 'author_name' => 'Jo', 'body' => 'x', 'status' => 'pending']);

    // No token → 401.
    $this->patchJson("/api/sites/{$site->name}/comments/{$comment->id}", ['status' => 'approved'])->assertUnauthorized();
});
