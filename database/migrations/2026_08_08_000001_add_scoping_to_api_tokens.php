<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * API-token hardening: hashed-at-rest storage, per-site + per-ability
 * scoping, expiry, and a display preview.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('api_tokens', 'site_id')) {
                $table->foreignId('site_id')->nullable()->after('user_id')
                    ->constrained('sites')->nullOnDelete();
            }
            if (! Schema::hasColumn('api_tokens', 'abilities')) {
                $table->json('abilities')->nullable()->after('token');
            }
            if (! Schema::hasColumn('api_tokens', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('abilities');
            }
            if (! Schema::hasColumn('api_tokens', 'token_preview')) {
                $table->string('token_preview', 12)->nullable()->after('token');
            }
        });

        // Legacy plaintext tokens (anything that isn't a 64-char hex digest)
        // are converted to their sha256 so the hashed lookup finds them —
        // the original raw value keeps working as the bearer.
        foreach (DB::table('api_tokens')->get(['id', 'token']) as $row) {
            $isDigest = strlen($row->token) === 64 && ctype_xdigit($row->token);
            DB::table('api_tokens')->where('id', $row->id)->update([
                'token' => $isDigest ? $row->token : hash('sha256', $row->token),
                'token_preview' => $isDigest ? null : substr($row->token, 0, 8),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
            $table->dropColumn(['abilities', 'expires_at', 'token_preview']);
        });
    }
};
