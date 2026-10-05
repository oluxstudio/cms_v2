<?php

use App\Livewire\PostsPage;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

function pdlPost(Site $site): Post
{
    return Post::create(['site_id' => $site->id, 'title' => 'Deep '.uniqid(), 'slug' => 'deep-'.Str::lower(Str::random(8)),
        'excerpt' => 'Intro', 'body' => '<h2>Hello</h2><p>World</p>', 'status' => 'published']);
}

test('?post= opens that post in the posts page editor, only for its own site and only for managers', function () {
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'pdl-'.uniqid().'.test']);
    $other = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'pdl-'.uniqid().'.test']);
    $post = pdlPost($site);
    $foreign = pdlPost($other);

    Livewire::withQueryParams(['post' => $post->id])->actingAs($owner)->test(PostsPage::class, ['site' => $site])
        ->assertSet('showForm', true)->assertSet('editingId', $post->id)->assertSet('title', $post->title);

    Livewire::withQueryParams(['post' => $foreign->id])->actingAs($owner)->test(PostsPage::class, ['site' => $site])
        ->assertSet('showForm', false)->assertSet('editingId', null);
});
