<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-edited membership plans. Each row overlays (or adds) one tier
        // of config/plans.php at boot; the reserved key "_settings" holds
        // plan-wide values such as trial_days.
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->json('data');
            $table->timestamps();
        });

        // Admin uploads go to the store (draft → publish) instead of the
        // uploader's private library.
        Schema::table('template_uploads', function (Blueprint $table) {
            $table->boolean('for_store')->default(false)->after('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
        Schema::table('template_uploads', fn (Blueprint $t) => $t->dropColumn('for_store'));
    }
};
