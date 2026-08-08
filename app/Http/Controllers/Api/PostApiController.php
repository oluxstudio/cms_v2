<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public blog API — consumed by the template-folder site apps:
 *
 *   GET  /api/sites/{site}/posts             → published posts (paginated)
 *   GET  /api/sites/{site}/posts/{slug}      → one published post (full HTML body)
 *   POST /api/sites/{site}/posts/{slug}/view → count a visit   (feeds the tiles)
 *   POST /api/sites/{site}/posts/{slug}/like → count a like    (feeds engagement)
 */
class PostApiController extends Controller
{
    use \App\Http\Controllers\Api\Concerns\ResolvesApiSite;

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

    public function index(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $posts = Post::where('site_id', $site->id)
            ->where('status', 'published')
            ->with('author:id,name')
            ->orderByDesc('published_at')
            ->paginate(min(50, max(1, (int) $request->query('per_page', 10))));

        return response()->json([
            'posts' => collect($posts->items())->map(fn (Post $p) => $this->summary($p)),
            'total' => $posts->total(),
            'page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
        ]);
    }

    public function show(string $siteName, string $slug): JsonResponse
    {
        $post = $this->post($this->site($siteName), $slug)->load('author:id,name');

        return response()->json($this->summary($post) + ['body' => (string) $post->body]);
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
        return [
            'title' => $p->title,
            'slug' => $p->slug,
            'excerpt' => $p->excerpt,
            'cover_image' => $p->cover_image,
            'author' => $p->author?->name,
            'published_at' => $p->published_at?->toIso8601String(),
            'views' => (int) $p->views,
            'likes' => (int) $p->likes,
            'comments' => (int) $p->comments,
        ];
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
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
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
            'status' => $status,
            'published_at' => $data['published_at'] ?? ($status === 'published' ? now() : null),
        ]);

        return response()->json(['ok' => true, 'post' => $this->record($post->load('author:id,name'))], 201);
    }

    public function update(Request $request, string $siteName, string $slug): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'posts.manage');
        $post = Post::where('site_id', $site->id)->where('slug', $slug)->firstOrFail();
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
