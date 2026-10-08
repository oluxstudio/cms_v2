<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Posts content module — create/manage blog posts. Tri-rail layout: stat tiles
 * (left), the filterable grid/list of posts (center), and summaries — status
 * breakdown, needs attention, comment moderation, most read (right).
 */
class PostsPage extends Component
{
    use WithLayoutMode;
    use WithPagination;

    /** Drafts untouched for this many days count as "needs attention". */
    public const STALE_DRAFT_DAYS = 14;

    public const FILTERS = ['all', 'published', 'draft', 'attention', 'comments'];

    public const SORTS = ['recent', 'updated', 'views', 'engagement', 'title'];

    public Site $site;

    #[Url(as: 'q')]
    public string $search = '';

    /** List filter: all | published | draft | attention | comments */
    #[Url(except: 'all')]
    public string $filter = 'all';

    /** Order: recent | updated | views | engagement | title */
    #[Url(except: 'recent')]
    public string $sort = 'recent';

    // Create / edit form (modal)
    public bool $showForm = false;

    public ?string $editingId = null;

    public string $title = '';

    public string $excerpt = '';

    public string $body = '';

    public string $coverImage = '';

    public string $category = '';

    /** Comma-separated in the form; stored as a json array. */
    public string $tags = '';

    public string $status = 'draft';

    /** ?post={id} — open that post straight in the editor (e.g. from the Edit page's post panel). */
    #[Url(as: 'post')]
    public ?string $openPost = null;

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('posts', 'grid');
        if (! in_array($this->filter, self::FILTERS, true)) {
            $this->filter = 'all';
        }
        if (! in_array($this->sort, self::SORTS, true)) {
            $this->sort = 'recent';
        }
        if ($this->openPost && $this->canManage() && Post::where('site_id', $site->id)->whereKey($this->openPost)->exists()) {
            $this->editPost($this->openPost);
        }
        $this->openPost = null; // one-shot: closing the editor shouldn't re-open it on refresh
    }

    private function canManage(): bool
    {
        return $this->site->canManageTeam(Auth::user());
    }

    // ── Create / edit ────────────────────────────────────────────

    public function createPost(): void
    {
        abort_unless($this->canManage(), 403);
        $this->reset(['editingId', 'title', 'excerpt', 'body', 'coverImage', 'category', 'tags']);
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
        // Library images in the body are stored as @media refs; the editor needs real URLs.
        $this->body = Media::resolveHtml($this->site->id, $post->body);
        $this->coverImage = (string) $post->cover_image;
        $this->category = (string) $post->category;
        $this->tags = implode(', ', $post->tags ?? []);
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
            'category' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:600'],
            'status' => ['required', 'in:draft,published'],
        ]);
        $tags = array_values(array_filter(array_map('trim', explode(',', $this->tags))));

        if ($this->editingId) {
            $post = Post::where('site_id', $this->site->id)->findOrFail($this->editingId);
            $post->update([
                'title' => $this->title,
                'excerpt' => $this->excerpt ?: null,
                'body' => $this->body ? Media::refHtml($this->site->id, $this->body) : null,
                'cover_image' => $this->coverImage ?: null,
                'category' => $this->category ?: null,
                'tags' => $tags ?: null,
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
                'body' => $this->body ? Media::refHtml($this->site->id, $this->body) : null,
                'cover_image' => $this->coverImage ?: null,
                'category' => $this->category ?: null,
                'tags' => $tags ?: null,
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

    // ── Comment moderation (pending → approved / spam) ───────────

    public function moderateComment(string $id, string $status): void
    {
        abort_unless($this->canManage(), 403);
        abort_unless(in_array($status, ['approved', 'spam'], true), 422);
        $comment = Comment::where('site_id', $this->site->id)->findOrFail($id);
        $wasApproved = $comment->status === 'approved';
        $comment->update(['status' => $status]);

        // Keep the post's cached approved-count in sync (same rule as the comments API).
        if ($status === 'approved' && ! $wasApproved) {
            $comment->post?->increment('comments');
        } elseif ($status !== 'approved' && $wasApproved) {
            $comment->post?->decrement('comments');
        }
    }

    // ── Filter / sort ────────────────────────────────────────────

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filter = 'all';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        if (! in_array($this->sort, self::SORTS, true)) {
            $this->sort = 'recent';
        }
        $this->resetPage();
    }

    // ── Data ─────────────────────────────────────────────────────

    /** Distinct categories already used on this site — feeds the datalist. */
    public function getCategoriesProperty(): array
    {
        return Post::where('site_id', $this->site->id)
            ->whereNotNull('category')->distinct()->orderBy('category')
            ->pluck('category')->all();
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

    /** Posts missing a cover or excerpt, or drafts left untouched for a while. */
    private function scopeNeedsAttention(Builder $q): Builder
    {
        $stale = now()->subDays(self::STALE_DRAFT_DAYS);

        return $q->where(fn ($w) => $w
            ->whereNull('cover_image')->orWhere('cover_image', '')
            ->orWhereNull('excerpt')->orWhere('excerpt', '')
            ->orWhere(fn ($d) => $d->where('status', 'draft')->where('updated_at', '<', $stale)));
    }

    /** Every rail number in ONE aggregate query. */
    private function stats(): array
    {
        $stale = now()->subDays(self::STALE_DRAFT_DAYS);
        $week = now()->subDays(7);
        $row = Post::where('site_id', $this->site->id)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published")
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts")
            ->selectRaw("SUM(CASE WHEN status = 'published' AND published_at >= ? THEN 1 ELSE 0 END) as published_week", [$week])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as created_week', [$week])
            ->selectRaw("SUM(CASE WHEN cover_image IS NULL OR cover_image = '' THEN 1 ELSE 0 END) as no_cover")
            ->selectRaw("SUM(CASE WHEN excerpt IS NULL OR excerpt = '' THEN 1 ELSE 0 END) as no_excerpt")
            ->selectRaw("SUM(CASE WHEN status = 'draft' AND updated_at < ? THEN 1 ELSE 0 END) as stale_drafts", [$stale])
            ->selectRaw("SUM(CASE WHEN cover_image IS NULL OR cover_image = '' OR excerpt IS NULL OR excerpt = '' OR (status = 'draft' AND updated_at < ?) THEN 1 ELSE 0 END) as attention", [$stale])
            ->selectRaw('COALESCE(SUM(views),0) as views')
            ->selectRaw('COALESCE(SUM(likes),0) as likes')
            ->selectRaw('COALESCE(SUM(comments),0) as comments')
            ->first();

        $stats = collect((array) $row)->map(fn ($v) => (int) $v)->all();

        $pending = Comment::where('site_id', $this->site->id)->where('status', 'pending');
        $stats['pending_comments'] = (clone $pending)->count();
        $stats['posts_with_pending'] = (clone $pending)->distinct()->count('post_id');

        return $stats;
    }

    public function render()
    {
        $siteId = $this->site->id;
        $needle = trim($this->search);

        $posts = Post::where('site_id', $siteId)
            ->with('author:id,name')
            ->withCount(['commentThread as pending_comments_count' => fn ($q) => $q->where('status', 'pending')])
            ->when($needle !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', '%'.$needle.'%')
                ->orWhere('excerpt', 'like', '%'.$needle.'%')
                ->orWhere('category', 'like', '%'.$needle.'%')))
            ->when($this->filter === 'published', fn ($q) => $q->where('status', 'published'))
            ->when($this->filter === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($this->filter === 'attention', fn ($q) => $this->scopeNeedsAttention($q))
            ->when($this->filter === 'comments', fn ($q) => $q->whereHas('commentThread', fn ($c) => $c->where('status', 'pending')))
            ->tap(fn ($q) => match ($this->sort) {
                'updated' => $q->orderByDesc('updated_at'),
                'views' => $q->orderByDesc('views')->orderByDesc('created_at'),
                'engagement' => $q->orderByRaw('(likes + comments) desc')->orderByDesc('created_at'),
                'title' => $q->orderBy('title'),
                default => $q->orderByDesc('created_at'),
            })
            ->orderByDesc('id')
            ->paginate(12);

        $stats = $this->stats();

        $byCategory = Post::where('site_id', $siteId)
            ->selectRaw("COALESCE(NULLIF(category, ''), 'Uncategorised') as cat, COUNT(*) as n")
            ->groupBy('cat')->orderByDesc('n')->limit(6)
            ->pluck('n', 'cat')->map(fn ($n) => (int) $n)->all();

        $mostRead = Post::where('site_id', $siteId)->where('views', '>', 0)
            ->orderByDesc('views')->limit(5)->get(['id', 'title', 'views', 'likes', 'comments']);

        $recentlyPublished = Post::where('site_id', $siteId)->where('status', 'published')
            ->orderByDesc('published_at')->limit(4)->get(['id', 'title', 'published_at']);

        $attentionList = $this->scopeNeedsAttention(Post::where('site_id', $siteId))
            ->orderBy('updated_at')->limit(4)
            ->get(['id', 'title', 'status', 'cover_image', 'excerpt', 'updated_at']);

        $pendingComments = Comment::where('site_id', $siteId)->where('status', 'pending')
            ->with('post:id,title')->latest()->limit(4)->get();

        return view('livewire.posts-page', [
            'posts' => $posts,
            'stats' => $stats,
            'byCategory' => $byCategory,
            'mostRead' => $mostRead,
            'recentlyPublished' => $recentlyPublished,
            'attentionList' => $attentionList,
            'pendingComments' => $pendingComments,
            'staleDays' => self::STALE_DRAFT_DAYS,
        ]);
    }
}
