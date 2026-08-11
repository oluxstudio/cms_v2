<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiSite;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Blog post comments.
 *
 *   GET    /sites/{site}/posts/{slug}/comments           → approved comments (public)
 *   POST   /sites/{site}/posts/{slug}/comments           → submit (public; stored pending)
 *   GET    /sites/{site}/posts/{slug}/comments/moderate  → all/filtered (Bearer · posts.manage)
 *   PATCH  /sites/{site}/comments/{id}                    → set status  (Bearer · posts.manage)
 *   DELETE /sites/{site}/comments/{id}                    → delete      (Bearer · posts.manage)
 *
 * The cached `posts.comments` count tracks APPROVED comments — kept in sync as
 * comments are approved / unapproved / deleted.
 */
class CommentApiController extends Controller
{
    use ResolvesApiSite;

    /** Public: approved comments for a published post. */
    public function index(string $siteName, string $slug): JsonResponse
    {
        $post = $this->publishedPost($siteName, $slug);

        return response()->json([
            'comments' => $post->commentThread()->approved()->latest()->get()
                ->map(fn (Comment $c) => $c->toApiArray())->values(),
        ]);
    }

    /** Public: submit a comment (held for moderation). */
    public function store(Request $request, string $siteName, string $slug): JsonResponse
    {
        $post = $this->publishedPost($siteName, $slug);

        $data = $request->validate([
            'author_name' => ['required', 'string', 'max:120'],
            'author_email' => ['nullable', 'email', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        Comment::create($data + [
            'post_id' => $post->id,
            'site_id' => $post->site_id,
            'status' => 'pending',
        ]);

        return response()->json(['ok' => true, 'message' => 'Thanks — your comment will appear once approved.'], 201);
    }

    /** Token: list comments in any status for moderation. */
    public function moderate(Request $request, string $siteName, string $slug): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'posts.manage');
        $post = Post::where('site_id', $site->id)->where('slug', $slug)->firstOrFail();

        $status = $request->query('status', 'all');
        $comments = $post->commentThread()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()->get()
            ->map(fn (Comment $c) => $c->toApiArray() + ['status' => $c->status, 'author_email' => $c->author_email]);

        return response()->json(['comments' => $comments->values()]);
    }

    /** Token: change a comment's moderation status. */
    public function update(Request $request, string $siteName, string $id): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'posts.manage');
        $comment = Comment::where('site_id', $site->id)->findOrFail($id);

        $data = $request->validate(['status' => ['required', 'in:pending,approved,spam']]);
        $this->applyStatus($comment, $data['status']);

        return response()->json(['ok' => true, 'comment' => $comment->toApiArray() + ['status' => $comment->status]]);
    }

    /** Token: delete a comment. */
    public function destroy(Request $request, string $siteName, string $id): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'posts.manage');
        $comment = Comment::where('site_id', $site->id)->findOrFail($id);

        if ($comment->status === 'approved') {
            $comment->post?->decrement('comments');
        }
        $comment->delete();

        return response()->json(['ok' => true]);
    }

    /** Move a comment's status, keeping the post's approved-count in sync. */
    private function applyStatus(Comment $comment, string $status): void
    {
        $was = $comment->status === 'approved';
        $now = $status === 'approved';
        $comment->update(['status' => $status]);

        if ($now && ! $was) {
            $comment->post?->increment('comments');
        } elseif (! $now && $was) {
            $comment->post?->decrement('comments');
        }
    }

    private function publishedPost(string $siteName, string $slug): Post
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        return Post::where('site_id', $site->id)->where('slug', $slug)->where('status', 'published')->firstOrFail();
    }
}
