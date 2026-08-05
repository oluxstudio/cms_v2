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
}
