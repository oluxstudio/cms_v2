<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lets recurring sweeps raise an alert once per subject (e.g. one per
        // overdue invoice) — firstOrCreate on this key instead of piling up.
        if (! Schema::hasColumn('alerts', 'dedupe_key')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->string('dedupe_key')->nullable()->after('type');
                $table->unique(['site_id', 'dedupe_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'dedupe_key']);
            $table->dropColumn('dedupe_key');
        });
    }
};
