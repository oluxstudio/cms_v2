<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Track each asset's real byte size so account storage usage can be summed
 * accurately (the human `size` string can't be added up). Backfills existing
 * rows from the file on disk, falling back to parsing the size label.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('media', 'bytes')) {
            Schema::table('media', fn (Blueprint $t) => $t->unsignedBigInteger('bytes')->default(0)->after('size'));
        }

        foreach (DB::table('media')->get(['id', 'url', 'size']) as $row) {
            $bytes = 0;
            if (str_starts_with((string) $row->url, '/storage/')) {
                $rel = str_replace('/storage/', '', $row->url);
                if (Storage::disk('public')->exists($rel)) {
                    $bytes = Storage::disk('public')->size($rel);
                }
            }
            if ($bytes === 0) {
                $bytes = $this->parseSize((string) $row->size);
            }
            DB::table('media')->where('id', $row->id)->update(['bytes' => $bytes]);
        }
    }

    private function parseSize(string $label): int
    {
        if (! preg_match('/([\d.]+)\s*(B|KB|MB|GB|TB)/i', $label, $m)) {
            return 0;
        }
        $mult = ['B' => 1, 'KB' => 1024, 'MB' => 1024 ** 2, 'GB' => 1024 ** 3, 'TB' => 1024 ** 4];

        return (int) round(((float) $m[1]) * ($mult[strtoupper($m[2])] ?? 1));
    }

    public function down(): void
    {
        Schema::table('media', fn (Blueprint $t) => $t->dropColumn('bytes'));
    }
};
