<?php

namespace App\Console\Commands;

use App\Models\Block;
use App\Models\BlockBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time in-place migration of v2 block rows to the v3 catalogue:
 *   text  → header (h1–h6 variants) or content (body/caption)
 *   image → media (kind image; alt backfilled — v3 requires it)
 *   embed → media (kind video, src carried)
 *   h_list/v_list → flex (row/column + prop maps)
 *   button/tile/card actions: url → target
 * Undo history is cleared — old snapshots reference retired types.
 */
class BlockkitMigrateV3 extends Command
{
    protected $signature = 'blockkit:migrate-v3 {--dry : Report what would change without writing}';

    protected $description = 'Migrate v2 BlockKit blocks to the v3 catalogue in place';

    private const JUSTIFY = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end', 'between' => 'space-between'];

    private const ALIGN = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end', 'stretch' => 'stretch'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $counts = [];

        DB::transaction(function () use ($dry, &$counts) {
            foreach (Block::query()->cursor() as $block) {
                $new = $this->transform($block);
                if ($new === null) {
                    continue;
                }
                $counts[$block->type.' → '.$new['type']] = ($counts[$block->type.' → '.$new['type']] ?? 0) + 1;
                if (! $dry) {
                    $block->update($new);
                }
            }
            if (! $dry) {
                BlockBatch::query()->delete(); // stale snapshots reference retired types
            }
        });

        $this->info(($dry ? '[dry-run] ' : '').'Migrated:');
        foreach ($counts as $change => $n) {
            $this->line("  {$n}× {$change}");
        }
        if ($counts === []) {
            $this->line('  nothing to migrate — all blocks already v3.');
        }

        return self::SUCCESS;
    }

    /** @return array{type:string,props:array}|null null = no change needed */
    private function transform(Block $block): ?array
    {
        $p = $block->props ?? [];

        return match ($block->type) {
            'text' => (function () use ($p) {
                $variant = $p['variant'] ?? 'body';
                if (preg_match('/^h[1-6]$/', $variant)) {
                    return ['type' => 'header', 'props' => array_filter([
                        'content' => (string) ($p['content'] ?? ''),
                        'level'   => $variant,
                        'align'   => $p['align'] ?? null,
                    ], fn ($v) => $v !== null)];
                }

                return ['type' => 'content', 'props' => array_filter([
                    'content' => (string) ($p['content'] ?? ''),
                    'size'    => $variant === 'caption' ? 'sm' : null,
                    'align'   => $p['align'] ?? null,
                ], fn ($v) => $v !== null)];
            })(),

            'image' => ['type' => 'media', 'props' => array_filter([
                'kind'        => 'image',
                'asset_id'    => $p['asset_id'] ?? null,
                'image_brief' => $p['image_brief'] ?? null,
                'alt'         => trim((string) ($p['alt'] ?? '')) ?: (data_get($block->meta, 'label') ?: 'Image'),
                'fit'         => $p['fit'] ?? null,
                'ratio'       => $p['ratio'] ?? null,
            ], fn ($v) => $v !== null)],

            'embed' => ['type' => 'media', 'props' => array_filter([
                'kind' => 'video',
                'src'  => (string) ($p['src'] ?? ''),
                'alt'  => null,
            ], fn ($v) => $v !== null)],

            'h_list' => ['type' => 'flex', 'props' => array_filter([
                'direction'       => 'row',
                'flex_wrap'       => ($p['wrap'] ?? true) ? 'wrap' : 'nowrap',
                'justify_content' => self::JUSTIFY[$p['justify'] ?? ''] ?? null,
                'align_items'     => self::ALIGN[$p['align'] ?? ''] ?? null,
                'gap'             => $p['gap'] ?? null,
            ], fn ($v) => $v !== null)],

            'v_list' => ['type' => 'flex', 'props' => array_filter([
                'direction'   => 'column',
                'flex_wrap'   => 'nowrap',
                'align_items' => self::ALIGN[$p['align'] ?? ''] ?? null,
                'gap'         => $p['gap'] ?? null,
            ], fn ($v) => $v !== null)],

            'button' => (function () use ($p) {
                if (! isset($p['action']['url'])) {
                    return null;
                }
                $action = $p['action'];
                $action['target'] = $action['target'] ?? $action['url'];
                unset($action['url']);

                return ['type' => 'button', 'props' => ['action' => $action] + $p];
            })(),

            default => null,
        };
    }
}
