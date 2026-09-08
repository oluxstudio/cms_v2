<?php

namespace App\Services\Ai;

use App\Jobs\SyncSiteKnowledge;
use App\Models\AiChunk;
use App\Models\Site;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * The tenant knowledge base (RAG): chunks the site's own content, embeds it
 * via local Ollama when available, and retrieves the passages most relevant
 * to a question. Everything is scoped by site_id — that single clause is the
 * whole multi-tenant isolation story.
 */
class SiteKnowledge
{
    private const CHUNK_CHARS = 1200;

    public function __construct(private Embedder $embedder) {}

    /** Queue a re-sync when the site's knowledge is older than an hour. */
    public function syncIfStale(Site $site): void
    {
        $latest = AiChunk::where('site_id', $site->id)->max('updated_at');
        if ($latest === null || now()->parse($latest)->lt(now()->subHour())) {
            SyncSiteKnowledge::dispatch($site->id);
        }
    }

    /** Rebuild the site's chunks from its current content (hash-skips unchanged). */
    public function sync(Site $site): int
    {
        $written = 0;
        foreach ($this->sources($site) as [$type, $id, $text]) {
            $text = trim(preg_replace('/\s+/', ' ', (string) $text));
            if (mb_strlen($text) < 20) {
                continue;
            }
            foreach (str_split($text, self::CHUNK_CHARS) as $no => $chunk) {
                $hash = sha1($chunk);
                $existing = AiChunk::where('site_id', $site->id)
                    ->where('source_type', $type)->where('source_id', (string) $id)->where('chunk_no', $no)
                    ->first();
                if ($existing && $existing->content_hash === $hash) {
                    continue; // unchanged — keep the stored embedding
                }
                AiChunk::updateOrCreate(
                    ['site_id' => $site->id, 'source_type' => $type, 'source_id' => (string) $id, 'chunk_no' => $no],
                    ['content' => $chunk, 'content_hash' => $hash, 'embedding' => $this->embedder->embed($chunk)],
                );
                $written++;
            }
        }
        // Touch freshness even when nothing changed, so staleness checks settle.
        if ($written === 0) {
            AiChunk::where('site_id', $site->id)->limit(1)->update(['updated_at' => now()]);
        }

        return $written;
    }

    /** @return list<string> the most relevant chunk texts for the question */
    public function retrieve(Site $site, string $question, int $k = 6): array
    {
        $chunks = AiChunk::where('site_id', $site->id)->get(['content', 'embedding']);
        if ($chunks->isEmpty()) {
            return [];
        }

        $qVector = $this->embedder->embed($question);
        if ($qVector) {
            $ranked = $chunks->filter(fn ($c) => is_array($c->embedding))
                ->map(fn ($c) => ['content' => $c->content, 'score' => Embedder::cosine($qVector, $c->embedding)])
                ->sortByDesc('score')->take($k)->filter(fn ($r) => $r['score'] > 0.3);
            if ($ranked->isNotEmpty()) {
                return $ranked->pluck('content')->values()->all();
            }
        }

        // Keyword fallback: score by how many question terms each chunk contains.
        $terms = collect(preg_split('/\W+/u', mb_strtolower($question)))
            ->filter(fn ($t) => mb_strlen($t) > 3)->unique()->values();
        if ($terms->isEmpty()) {
            return [];
        }

        return $chunks->map(fn ($c) => [
            'content' => $c->content,
            'score' => $terms->sum(fn ($t) => substr_count(mb_strtolower($c->content), $t)),
        ])->filter(fn ($r) => $r['score'] > 0)->sortByDesc('score')->take($k)->pluck('content')->values()->all();
    }

    /** @return iterable<array{0:string,1:string,2:string}> [type, id, text] */
    private function sources(Site $site): iterable
    {
        // The business itself.
        $attrs = collect(['business_name', 'description', 'phone', 'email', 'address', 'opening_hours'])
            ->map(fn ($k) => ($v = $site->getAttr($k)) ? Str::headline($k).': '.$v : null)->filter()->implode('. ');
        yield ['business', 'profile', 'About '.($site->getAttr('business_name') ?: $site->name).'. '.($site->description ?? '').' '.$attrs];

        // Page/component copy — every node value with its label.
        foreach ($site->contentComponents()->with('nodes')->get() as $component) {
            $text = $component->nodes->map(fn ($n) => trim(($n->label ? $n->label.': ' : '').(string) $n->value))
                ->filter()->implode('. ');
            yield ['component', (string) $component->id, $component->name.'. '.$text];
        }

        // Services (bookable offerings).
        foreach ($site->services()->get() as $service) {
            yield ['service', (string) $service->id,
                'Service: '.$service->name.'. '.($service->description ?? '')
                .(isset($service->price_cents) ? ' Price: '.Money::format((int) $service->price_cents, $site->currency ?? 'gbp').'.' : '')];
        }

        // Products.
        foreach ($site->products()->get() as $product) {
            yield ['product', (string) $product->id,
                'Product: '.$product->name.'. '.($product->description ?? '').' Price: '.$product->formattedPrice().'.'
                .($product->category ? ' Category: '.$product->category.'.' : '')];
        }

        // Collections (FAQs, testimonials, menus…).
        foreach ($site->collections()->with('items')->get() as $collection) {
            $text = $collection->items->map(fn ($i) => collect($i->data ?? [])->filter(fn ($v) => is_scalar($v))->implode('. '))->implode('. ');
            yield ['collection', (string) $collection->id, $collection->name.'. '.$text];
        }
    }
}
