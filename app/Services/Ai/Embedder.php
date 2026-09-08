<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

/**
 * Text → vector via the local Ollama embeddings API. Best-effort: returns
 * null on any failure so callers can fall back to keyword retrieval.
 */
class Embedder
{
    public function available(): bool
    {
        return filled(config('services.ollama.base_url'));
    }

    /** @return list<float>|null */
    public function embed(string $text): ?array
    {
        if (! $this->available() || trim($text) === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)->post(
                rtrim((string) config('services.ollama.base_url'), '/').'/api/embeddings',
                ['model' => config('services.ollama.embed_model', 'nomic-embed-text'), 'prompt' => $text],
            );
            $vector = $response->json('embedding');

            return is_array($vector) && $vector !== [] ? array_map('floatval', $vector) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Cosine similarity between two equal-length vectors. */
    public static function cosine(array $a, array $b): float
    {
        $dot = $na = $nb = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] ** 2;
            $nb += $b[$i] ** 2;
        }

        return ($na && $nb) ? $dot / (sqrt($na) * sqrt($nb)) : 0.0;
    }
}
