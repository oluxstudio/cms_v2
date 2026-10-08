<?php

use App\Livewire\PostsPage;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

function pprSite(): array
{
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'ppr-'.uniqid().'.test']);

    return [$owner, $site];
}

function pprPost(Site $site, array $attrs = []): Post
{
    $title = $attrs['title'] ?? 'Post '.uniqid();

    return Post::create(array_merge([
        'site_id' => $site->id,
        'title' => $title,
        'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
        'excerpt' => 'An excerpt',
        'cover_image' => 'https://example.test/cover.jpg',
        'status' => 'published',
        'published_at' => now(),
    ], $attrs));
}

/** Published+complete "Alpha", draft without cover "Bravo", stale draft "Charlie", published w/o excerpt "Delta". */
function pprSeed(Site $site): array
{
    $alpha = pprPost($site, ['title' => 'Alpha', 'views' => 50, 'likes' => 3]);
    $bravo = pprPost($site, ['title' => 'Bravo', 'status' => 'draft', 'published_at' => null, 'cover_image' => null, 'views' => 5]);
    $charlie = pprPost($site, ['title' => 'Charlie', 'status' => 'draft', 'published_at' => null, 'views' => 0]);
    Post::whereKey($charlie->id)->toBase()->update(['updated_at' => now()->subDays(30), 'created_at' => now()->subDays(30)]);
    $delta = pprPost($site, ['title' => 'Delta', 'excerpt' => null, 'views' => 200, 'likes' => 1]);

    Comment::create(['post_id' => $alpha->id, 'site_id' => $site->id, 'author_name' => 'Ann', 'body' => 'Nice one', 'status' => 'pending']);
    Comment::create(['post_id' => $alpha->id, 'site_id' => $site->id, 'author_name' => 'Ben', 'body' => 'Great', 'status' => 'pending']);
    Comment::create(['post_id' => $delta->id, 'site_id' => $site->id, 'author_name' => 'Cal', 'body' => 'Ok', 'status' => 'approved']);

    return compact('alpha', 'bravo', 'charlie', 'delta');
}

test('grid is the default layout for the posts page', function () {
    [$owner, $site] = pprSite();

    Livewire::actingAs($owner)->test(PostsPage::class, ['site' => $site])
        ->assertSet('viewMode', 'grid')
        ->assertSet('filter', 'all')
        ->assertSet('sort', 'recent');
});

test('rail stats count status, attention, comments and engagement', function () {
    [$owner, $site] = pprSite();
    pprSeed($site);

    $stats = Livewire::actingAs($owner)->test(PostsPage::class, ['site' => $site])->viewData('stats');

    expect($stats['total'])->toBe(4)
        ->and($stats['published'])->toBe(2)
        ->and($stats['drafts'])->toBe(2)
        ->and($stats['published_week'])->toBe(2)
        ->and($stats['no_cover'])->toBe(1)
        ->and($stats['no_excerpt'])->toBe(1)
        ->and($stats['stale_drafts'])->toBe(1)
        ->and($stats['attention'])->toBe(3)
        ->and($stats['views'])->toBe(255)
        ->and($stats['likes'])->toBe(4)
        ->and($stats['pending_comments'])->toBe(2)
        ->and($stats['posts_with_pending'])->toBe(1);
});

test('each filter narrows the listing', function (string $filter, array $expected) {
    [$owner, $site] = pprSite();
    pprSeed($site);

    $titles = Livewire::actingAs($owner)->test(PostsPage::class, ['site' => $site])
        ->call('setFilter', $filter)
        ->assertSet('filter', $filter)
        ->viewData('posts')->pluck('title')->sort()->values()->all();

    expect($titles)->toBe($expected);
})->with([
    'all' => ['all', ['Alpha', 'Bravo', 'Charlie', 'Delta']],
    'published' => ['published', ['Alpha', 'Delta']],
    'draft' => ['draft', ['Bravo', 'Charlie']],
    'attention' => ['attention', ['Bravo', 'Charlie', 'Delta']],
    'comments' => ['comments', ['Alpha']],
]);

test('an unknown filter falls back to all', function () {
    [$owner, $site] = pprSite();

    Livewire::actingAs($owner)->test(PostsPage::class, ['site' => $site])
        ->call('setFilter', 'bogus')->assertSet('filter', 'all');
});

test('sort orders the listing', function (string $sort, array $expected) {
    [$owner, $site] = pprSite();
    pprSeed($site);

    $titles = Livewire::actingAs($owner)->test(PostsPage::class, ['site' => $site])
        ->set('sort', $sort)
        ->viewData('posts')->pluck('title')->all();

    expect($titles)->toBe($expected);
})->with([
    'views' => ['views', ['Delta', 'Alpha', 'Bravo', 'Charlie']],
    'title' => ['title', ['Alpha', 'Bravo', 'Charlie', 'Delta']],
]);

test('search plus filter shows the nothing-matches empty state with a reset', function () {
    [$owner, $site] = pprSite();
    pprSeed($site);

    Livewire::actingAs($owner)->test(PostsPage::class, ['site' => $site])
        ->set('search', 'zzz-no-such-post')
        ->assertSee('Nothing matches')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSee('Alpha');
});

test('a site without posts shows the first-post empty state', function () {
    [$owner, $site] = pprSite();

    Livewire::actingAs($owner)->test(PostsPage::class, ['site' => $site])
        ->assertSee('No posts yet')
        ->assertSee('Write your first post');
});

test('renders the right-rail summaries and the list view', function () {
    [$owner, $site] = pprSite();
    pprSeed($site);

    Livewire::actingAs($owner)->test(PostsPage::class, ['site' => $site])
        ->assertSee('Posts summary')
        ->assertSee('Needs attention')
        ->assertSee('Most read')
        ->assertSee('Recently published')
        ->assertSee('Related')
        ->assertSee('Nice one')
        ->call('setViewMode', 'list')
        ->assertSet('viewMode', 'list')
        ->assertSee('Engagement');
});

test('approving a pending comment bumps the post comment count', function () {
    [$owner, $site] = pprSite();
    ['alpha' => $alpha] = pprSeed($site);
    $comment = Comment::where('post_id', $alpha->id)->where('author_name', 'Ann')->first();

    Livewire::actingAs($owner)->test(PostsPage::class, ['site' => $site])
        ->call('moderateComment', $comment->id, 'approved');

    expect($comment->fresh()->status)->toBe('approved')
        ->and($alpha->fresh()->comments)->toBe(1);
});
