<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiSite;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Site;
use App\Services\ContentVersioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public blog API — consumed by the template-folder site apps:
 *
 *   GET  /api/sites/{site}/posts             → published posts — filter by category/tag/author,
 *                                              exclude slugs, sort, page or offset+limit (see index)
 *   GET  /api/sites/{site}/posts/{slug}      → one published post (full HTML body)
 *   POST /api/sites/{site}/posts/{slug}/view → count a visit   (feeds the tiles)
 *   POST /api/sites/{site}/posts/{slug}/like → count a like    (feeds engagement)
 */
class PostApiController extends Controller
{
    use ResolvesApiSite;

    private function site(string $siteName): Site
    {
        return Site::where('name', $siteName)->firstOrFail();
    }

    private function post(Site $site, string $slug): Post
    {
        return Post::where('site_id', $site->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();
    }

    /**
     * Published posts. Every filter is optional and they combine:
     *   ?category=news   ?tag=styling   ?author=Jane%20Doe   (case-insensitive)
     *   ?exclude=slug-a,slug-b          leave these posts out (e.g. ones already featured)
     *   ?sort=newest|oldest|popular|liked|title   (default newest; popular = most views)
     * Paging — either pages:  ?page=2&per_page=12   (per_page ≤ 50, default 10)
     *          or a range:    ?offset=24&limit=12   (limit ≤ 50)
     * The response also lists `categories`: every category with published posts.
     */
    public function index(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $published = Post::where('site_id', $site->id)->where('status', 'published');
        // Public endpoint: only plain string params count (?x[]= arrays are ignored, never a 500).
        $str = fn (string $key): ?string => is_string($v = $request->query($key)) && $v !== '' ? $v : null;

        $query = (clone $published)
            ->when($str('category'), fn ($q, $c) => $q->whereRaw('LOWER(category) = ?', [mb_strtolower($c)]))
            ->when($str('tag'), fn ($q, $t) => $q->whereJsonContains('tags', $t))
            ->when($str('author'), fn ($q, $a) => $q->whereHas('author',
                fn ($u) => $u->whereRaw('LOWER(name) = ?', [mb_strtolower($a)])))
            ->when($str('exclude'), fn ($q, $e) => $q->whereNotIn('slug',
                array_slice(array_filter(array_map('trim', explode(',', $e))), 0, 50)))
            ->with('author:id,name');

        match ($str('sort') ?? 'newest') {
            'oldest' => $query->orderBy('published_at'),
            'popular' => $query->orderByDesc('views')->orderByDesc('published_at'),
            'liked' => $query->orderByDesc('likes')->orderByDesc('published_at'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('published_at'),
        };

        $categories = (clone $published)->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category')->values();

        // Range mode: ?offset=&limit=
        if ($request->filled('limit') || $request->filled('offset')) {
            $limit = min(50, max(1, (int) ($str('limit') ?? 10)));
            $offset = max(0, (int) ($str('offset') ?? 0));
            $total = (clone $query)->count();
            $posts = $query->skip($offset)->take($limit)->get();

            return response()->json([
                'posts' => $posts->map(fn (Post $p) => $this->summary($p)),
                'total' => $total,
                'offset' => $offset,
                'limit' => $limit,
                'page' => intdiv($offset, $limit) + 1,
                'last_page' => max(1, (int) ceil($total / $limit)),
                'categories' => $categories,
            ]);
        }

        $posts = $query->paginate(min(50, max(1, (int) ($str('per_page') ?? 10))));

        return response()->json([
            'posts' => collect($posts->items())->map(fn (Post $p) => $this->summary($p)),
            'total' => $posts->total(),
            'page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'categories' => $categories,
        ]);
    }

    public function show(string $siteName, string $slug): JsonResponse
    {
        $post = $this->post($this->site($siteName), $slug)->load('author:id,name');

        // Detail view embeds the full HTML body + approved comments (and moves
        // the count to `comments_count`).
        return response()->json($post->toApiArray(withBody: true, withComments: true));
    }

    /** Count a visit — the template site pings this from the post page. */
    public function view(string $siteName, string $slug): JsonResponse
    {
        $post = $this->post($this->site($siteName), $slug);
        $post->increment('views');

        return response()->json(['ok' => true, 'views' => $post->views]);
    }

    /** Count a like (♥ button on the public post). */
    public function like(string $siteName, string $slug): JsonResponse
    {
        $post = $this->post($this->site($siteName), $slug);
        $post->increment('likes');

        return response()->json(['ok' => true, 'likes' => $post->likes]);
    }

    private function summary(Post $p): array
    {
        return $p->toApiArray();
    }

    /* ── CRUD (Bearer token · posts.manage) ─────────────────────────────── */

    /** Full record for CRUD responses — includes drafts, id, status and body. */
    private function record(Post $p): array
    {
        return ['id' => $p->id, 'status' => $p->status] + $this->summary($p) + [
            'body' => (string) $p->body,
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    private function validated(Request $request, bool $creating): array
    {
        // Tags arrive as an array OR a comma-separated string — normalize first.
        if (is_string($request->input('tags'))) {
            $request->merge(['tags' => array_values(array_filter(array_map('trim', explode(',', $request->input('tags')))))]);
        }

        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'category' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:60'],
            'status' => ['sometimes', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'posts.manage');
        $data = $this->validated($request, creating: true);
        $status = $data['status'] ?? 'draft';

        $post = Post::create([
            'site_id' => $site->id,
            'user_id' => $request->attributes->get('api_token_user')?->id,
            'title' => $data['title'],
            'slug' => Post::uniqueSlug($site->id, $data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'] ?? '',
            'cover_image' => $data['cover_image'] ?? null,
            'category' => $data['category'] ?? null,
            'tags' => $data['tags'] ?? null,
            'status' => $status,
            'published_at' => $data['published_at'] ?? ($status === 'published' ? now() : null),
        ]);

        return response()->json(['ok' => true, 'post' => $this->record($post->load('author:id,name'))], 201);
    }

    public function update(Request $request, string $siteName, string $slug): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'posts.manage');
        $post = Post::where('site_id', $site->id)->where('slug', $slug)->firstOrFail();
        app(ContentVersioner::class)->capture($post, $request->attributes->get('api_token_user')?->name);
        $data = $this->validated($request, creating: false);

        $post->fill($data);
        if ($post->status === 'published' && ! $post->published_at) {
            $post->published_at = now();
        }
        $post->save();

        return response()->json(['ok' => true, 'post' => $this->record($post->load('author:id,name'))]);
    }

    public function destroy(Request $request, string $siteName, string $slug): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'posts.manage');
        Post::where('site_id', $site->id)->where('slug', $slug)->firstOrFail()->delete();

        return response()->json(['ok' => true]);
    }
}
