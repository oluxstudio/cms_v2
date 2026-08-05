<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field-composed custom types: which parameter fields a type exposes.
 * Guarded per-step (MySQL DDL is non-transactional).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('booking_types', 'fields')) {
            Schema::table('booking_types', function (Blueprint $table) {
                $table->json('fields')->nullable()->after('defaults');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_types', 'fields')) {
            Schema::table('booking_types', fn (Blueprint $t) => $t->dropColumn('fields'));
        }
    }
};
