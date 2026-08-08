<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Posts content module — create/manage blog posts with visit + engagement
 * insight tiles and top-10 rankings.
 */
class PostsPage extends Component
{
    use WithPagination;

    public Site $site;

    #[Url(as: 'q')]
    public string $search = '';

    // Create / edit form (modal)
    public bool $showForm = false;

    public ?string $editingId = null;

    public string $title = '';

    public string $excerpt = '';

    public string $body = '';

    public string $coverImage = '';

    public string $status = 'draft';

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    private function canManage(): bool
    {
        return $this->site->canManageTeam(Auth::user());
    }

    // ── Create / edit ────────────────────────────────────────────

    public function createPost(): void
    {
        abort_unless($this->canManage(), 403);
        $this->reset(['editingId', 'title', 'excerpt', 'body', 'coverImage']);
        $this->status = 'draft';
        $this->showForm = true;
    }

    public function editPost(string $id): void
    {
        abort_unless($this->canManage(), 403);
        $post = Post::where('site_id', $this->site->id)->findOrFail($id);
        $this->editingId = $post->id;
        $this->title = $post->title;
        $this->excerpt = (string) $post->excerpt;
        $this->body = (string) $post->body;
        $this->coverImage = (string) $post->cover_image;
        $this->status = $post->status;
        $this->showForm = true;
    }

    public function savePost(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:65000'],
            'coverImage' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:draft,published'],
        ]);

        if ($this->editingId) {
            $post = Post::where('site_id', $this->site->id)->findOrFail($this->editingId);
            $post->update([
                'title' => $this->title,
                'excerpt' => $this->excerpt ?: null,
                'body' => $this->body ?: null,
                'cover_image' => $this->coverImage ?: null,
                'status' => $this->status,
                'published_at' => $this->status === 'published' ? ($post->published_at ?? now()) : null,
            ]);
        } else {
            Post::create([
                'site_id' => $this->site->id,
                'user_id' => Auth::id(),
                'title' => $this->title,
                'slug' => Post::uniqueSlug($this->site->id, $this->title),
                'excerpt' => $this->excerpt ?: null,
                'body' => $this->body ?: null,
                'cover_image' => $this->coverImage ?: null,
                'status' => $this->status,
                'published_at' => $this->status === 'published' ? now() : null,
            ]);
        }

        $this->showForm = false;
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Post saved.');
    }

    public function togglePublish(string $id): void
    {
        abort_unless($this->canManage(), 403);
        $post = Post::where('site_id', $this->site->id)->findOrFail($id);
        $post->update([
            'status' => $post->isPublished() ? 'draft' : 'published',
            'published_at' => $post->isPublished() ? null : ($post->published_at ?? now()),
        ]);
    }

    public function deletePost(string $id): void
    {
        abort_unless($this->canManage(), 403);
        Post::where('site_id', $this->site->id)->whereKey($id)->delete();
    }

    // ── Data ─────────────────────────────────────────────────────

    /** Tiles: totals for the header cards. */
    public function getStatsProperty(): array
    {
        $q = Post::where('site_id', $this->site->id);

        return [
            'total' => (clone $q)->count(),
            'published' => (clone $q)->where('status', 'published')->count(),
            'views' => (int) (clone $q)->sum('views'),
            'engagement' => (int) (clone $q)->selectRaw('COALESCE(SUM(likes + comments),0) as e')->value('e'),
        ];
    }

    /** Site media (images + videos) for the editor's insert-asset lightbox. */
    public function getMediaAssetsProperty(): array
    {
        return $this->site->media()
            ->whereIn('file_type', ['image', 'video'])
            ->latest()->limit(120)
            ->get(['id', 'name', 'file_type', 'url'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'type' => $m->file_type, 'url' => $m->publicUrl()])
            ->all();
    }

    public function getTopByViewsProperty()
    {
        return Post::where('site_id', $this->site->id)->orderByDesc('views')->limit(10)->get();
    }

    public function getTopByEngagementProperty()
    {
        return Post::where('site_id', $this->site->id)
            ->orderByRaw('(likes + comments) desc')->limit(10)->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $posts = Post::where('site_id', $this->site->id)
            ->with('author:id,name')
            ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->latest()
            ->paginate(10);

        return view('livewire.posts-page', ['posts' => $posts]);
    }
}
