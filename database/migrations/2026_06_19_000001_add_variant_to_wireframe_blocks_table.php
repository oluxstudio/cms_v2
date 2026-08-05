<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wireframe_blocks', function (Blueprint $table) {
            $table->string('variant')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('wireframe_blocks', function (Blueprint $table) {
            $table->dropColumn('variant');
        });
    }
};
