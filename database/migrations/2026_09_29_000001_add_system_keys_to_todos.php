<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A task the platform creates and keeps in step with a definition
        // in code (e.g. 'setup'); items carry the definition's step key.
        Schema::table('todos', function (Blueprint $table) {
            $table->string('system_key', 40)->nullable()->after('site_id');
            $table->index(['site_id', 'system_key']);
        });
        Schema::table('todo_items', function (Blueprint $table) {
            $table->string('key', 60)->nullable()->after('todo_id');
        });
    }

    public function down(): void
    {
        Schema::table('todo_items', fn (Blueprint $table) => $table->dropColumn('key'));
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'system_key']);
            $table->dropColumn('system_key');
        });
    }
};
