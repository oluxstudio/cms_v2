<?php

namespace App\Console\Commands;

use App\Models\Block;
use App\Support\RichText;
use Illuminate\Console\Command;

/**
 * One-time backfill: rows saved BEFORE sanitize-at-save may hold raw HTML
 * (the live renderer used to v-html it unsanitized). Clean them in place.
 */
class SanitizeBlockContent extends Command
{
    protected $signature = 'blocks:sanitize-content {--dry : Report only, change nothing}';

    protected $description = 'Sanitize stored rich text on header/content blocks (allow-list inline HTML)';

    public function handle(): int
    {
        $dirty = 0;
        Block::whereIn('type', ['header', 'content'])->chunkById(200, function ($blocks) use (&$dirty) {
            foreach ($blocks as $block) {
                $raw = (string) data_get($block->props, 'content', '');
                $clean = RichText::clean($raw);
                if ($clean !== $raw) {
                    $dirty++;
                    $this->line("  {$block->id}: ".mb_substr($raw, 0, 60).'  →  '.mb_substr($clean, 0, 60));
                    if (! $this->option('dry')) {
                        $props = $block->props;
                        $props['content'] = $clean;
                        $block->update(['props' => $props]);
                    }
                }
            }
        });
        $this->info(($this->option('dry') ? '[dry] ' : '')."{$dirty} block(s) needed cleaning.");

        return self::SUCCESS;
    }
}
