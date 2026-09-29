<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-edited values layered over config/*.php at boot (ConfigOverlay):
        // key = config path (e.g. "domains.tlds"), data = {value, mode}.
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
