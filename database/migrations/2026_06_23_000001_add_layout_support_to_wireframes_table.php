<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wireframes', function (Blueprint $table) {
            // A wireframe is either a 'page' layout (tied to a page_id) or a reusable
            // 'layout' wrapper (page_id null) that wraps pages around a content slot.
            $table->string('name')->nullable()->after('title');
            $table->string('kind')->default('page')->after('name');
            $table->boolean('is_default')->default(false)->after('kind');

            $table->index(['site_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('wireframes', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'kind']);
            $table->dropColumn(['name', 'kind', 'is_default']);
        });
    }
};
