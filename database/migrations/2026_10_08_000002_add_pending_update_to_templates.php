<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Built-in templates live in the CMS repo; a deploy that changes one flags it
 * here ({version, hash, detected_at}) so Admin › Templates offers
 * "Update template" — nothing reaches clients until a super admin applies it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->json('pending_update')->nullable()->after('reset_collections');
        });
    }

    public function down(): void
    {
        Schema::table('templates', fn (Blueprint $table) => $table->dropColumn('pending_update'));
    }
};
