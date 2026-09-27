<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // null = every page; a list of page ids = CRUD only those pages
            // (and no creating new ones). Only meaningful with pages.manage.
            $table->json('page_scope')->nullable()->after('permissions');
            // The role editor now uses a textarea — allow long descriptions.
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('page_scope');
            $table->string('description')->nullable()->change();
        });
    }
};
