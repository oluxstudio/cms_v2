<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wireframe_blocks', function (Blueprint $table) {
            $table->foreignId('component_id')->nullable()->after('label')->constrained()->nullOnDelete();
            $table->json('settings')->nullable()->after('component_id');
        });
    }

    public function down(): void
    {
        Schema::table('wireframe_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('component_id');
            $table->dropColumn('settings');
        });
    }
};
