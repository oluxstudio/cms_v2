<?php

namespace App\GraphQL;

use App\Models\Component;
use App\Models\Form;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * All resolvers for the read-only content graph. Public requests see
 * published/public data only; a valid Bearer token (OptionalApiToken)
 * widens each field according to its permission — the exact same rules
 * as the REST surface, so the two can never diverge on visibility.
 */
class SiteGraph
{
    private const MAX_LIMIT = 100;

    public function site($root, array $args): ?Site
    {
        return Site::where('name', $args['name'])->first();
    }

    public function attributes(Site $site): array
    {
        return $site->attrMap();
    }

    public function pages(Site $site, array $args, GraphQLContext $context): array
    {
        $all = $this->widened($context, $site, 'pages.view');

        return $site->pages()
            ->when(! $all, fn ($q) => $q->where('is_published', true))
            ->orderBy('id')->limit($this->cap($args))->get()
            ->map(fn (Page $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'url' => $p->url,
                'keywords' => $p->keywords,
                'is_published' => (bool) $p->is_published,
                'attributes' => $p->attrMap(),
                'components' => $p->components()->with('nodes')->get()
                    ->map(fn (Component $c) => $c->payload())->values()->all(),
            ])->values()->all();
    }

    public function components(Site $site, array $args, GraphQLContext $context): array
    {
        return $site->contentComponents()->with('nodes')
            ->when($args['tag'] ?? null, fn ($q, $tag) => $q->whereJsonContains('tags', $tag))
            ->limit($this->cap($args))->get()
            ->map(fn (Component $c) => $c->payload())->values()->all();
    }

    public function posts(Site $site, array $args, GraphQLContext $context): array
    {
        $all = $this->widened($context, $site, 'posts.view');

        return Post::where('site_id', $site->id)
            ->when(! $all, fn ($q) => $q->where('status', 'published'))
            ->with('author:id,name')->orderByDesc('published_at')
            ->limit($this->cap($args))->get()
            ->map(fn (Post $p) => $this->postShape($p))->values()->all();
    }

    public function post(Site $site, array $args, GraphQLContext $context): ?array
    {
        $all = $this->widened($context, $site, 'posts.view');
        $post = Post::where('site_id', $site->id)->where('slug', $args['slug'])
            ->when(! $all, fn ($q) => $q->where('status', 'published'))
            ->with('author:id,name')->first();

        return $post ? $this->postShape($post) : null;
    }

    public function collections(Site $site, array $args, GraphQLContext $context): array
    {
        $all = $this->widened($context, $site, 'collections.view');

        return $site->collections()->with('items')
            ->when(! $all, fn ($q) => $q->where('is_public', true))
            ->limit($this->cap($args))->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'type' => $c->type,
                'description' => $c->description,
                'fields' => $c->fields ?? [],
                'items' => ($all ? $c->items : $c->items->where('status', 'published'))
                    ->map(fn ($i) => [
                        'id' => $i->id,
                        'data' => $i->data ?? [],
                        'status' => $i->status,
                        'created_at' => $i->created_at?->toIso8601String(),
                    ])->values()->all(),
            ])->values()->all();
    }

    public function media(Site $site, array $args): array
    {
        return Media::where('site_id', $site->id)
            ->when($args['type'] ?? null, fn ($q, $type) => $q->where('file_type', $type))
            ->latest()->limit($this->cap($args))->get()
            ->map(fn (Media $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $m->file_type,
                'url' => $m->publicUrl(),
                'alt' => $m->alt_text,
                'size' => $m->size,
            ])->values()->all();
    }

    public function forms(Site $site, array $args, GraphQLContext $context): array
    {
        $all = $this->widened($context, $site, 'forms.view');

        return Form::where('site_id', $site->id)
            ->when(! $all, fn ($q) => $q->where('is_active', true))
            ->limit($this->cap($args))->get()
            ->map(fn (Form $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'title' => $f->displayTitle(),
                'description' => $f->description,
                'fields' => $f->fields ?? [],
                'submit_url' => route('api.form', ['siteName' => $site->name, 'formName' => $f->name]),
            ])->values()->all();
    }

    /* ── Helpers ────────────────────────────────────────────────────────── */

    private function cap(array $args): int
    {
        return min(max((int) ($args['limit'] ?? 25), 1), self::MAX_LIMIT);
    }

    /** A valid token widens a field when user, token scope and ability all agree. */
    private function widened(GraphQLContext $context, Site $site, string $ability): bool
    {
        $request = $context->request();
        $user = $request?->attributes->get('api_token_user');
        $token = $request?->attributes->get('api_token');

        return $user && $token
            && $site->allows($user, $ability)
            && $token->allowsSite($site)
            && $token->can($ability);
    }

    private function postShape(Post $p): array
    {
        return [
            'title' => $p->title,
            'slug' => $p->slug,
            'excerpt' => $p->excerpt,
            'body' => (string) $p->body,
            'cover_image' => $p->cover_image,
            'author' => $p->author?->name,
            'status' => $p->status,
            'published_at' => $p->published_at?->toIso8601String(),
            'views' => (int) $p->views,
            'likes' => (int) $p->likes,
        ];
    }
}
