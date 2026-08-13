<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'onboarding')) {
                // { role, goal, welcomed_at, dismissed_at, skipped } — null = brand-new.
                $table->json('onboarding')->nullable()->after('theme');
            }
        });

        // Existing users are already up and running — mark them fully onboarded so
        // the welcome flow only ever shows to genuinely new signups.
        $now = now()->toIso8601String();
        DB::table('users')->whereNull('onboarding')->update([
            'onboarding' => json_encode(['welcomed_at' => $now, 'dismissed_at' => $now]),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'onboarding')) {
                $table->dropColumn('onboarding');
            }
        });
    }
};
