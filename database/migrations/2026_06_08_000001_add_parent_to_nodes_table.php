<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            // 0 = root node; otherwise the id of another node in the same component.
            $table->unsignedBigInteger('parent')->default(0)->after('component_id');
            $table->index(['component_id', 'parent']);
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropIndex(['component_id', 'parent']);
            $table->dropColumn('parent');
        });
    }
};
