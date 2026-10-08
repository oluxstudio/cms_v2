<?php

use App\Models\Media;
use Illuminate\Database\Migrations\Migration;

/**
 * Assets stored as "document" whose file is plainly an image / video / audio /
 * font (imported without a usable MIME type) get their real type — so the
 * Assets page previews them and labels them correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Media::where('file_type', 'document')->get(['id', 'name', 'url'])->each(function (Media $m) {
            $file = basename((string) parse_url((string) $m->url, PHP_URL_PATH)) ?: (string) $m->name;
            $type = Media::guessType(null, $file);
            if ($type === 'document' && $m->name) {
                $type = Media::guessType(null, (string) $m->name);
            }
            if ($type !== 'document') {
                Media::whereKey($m->id)->update(['file_type' => $type]);
            }
        });
    }

    public function down(): void
    {
        // Corrections only.
    }
};
