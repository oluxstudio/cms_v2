<?php

use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-classify existing media by file extension so SVGs (previously bucketed
 * as 'document' because MIME sniffing returns text/*) become 'image', and
 * fonts/audio get their own categories. Uses the same extension map as
 * Media::guessType.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('media')->get(['id', 'name', 'url', 'file_type']) as $row) {
            $ext = strtolower(pathinfo($row->name ?: $row->url, PATHINFO_EXTENSION));
            $guess = Media::guessType(null, 'x.'.$ext);
            // Only correct rows the extension confidently re-buckets (svg/fonts/audio);
            // never downgrade a real image/video the extension can't see.
            if ($guess !== 'document' && $guess !== $row->file_type) {
                DB::table('media')->where('id', $row->id)->update(['file_type' => $guess]);
            }
        }
    }

    public function down(): void
    {
        // No-op — the re-bucketing is a correctness fix, not reversible state.
    }
};
