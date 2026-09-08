<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('due_at');
        });
        Schema::table('todo_items', function (Blueprint $table) {
            $table->text('description')->nullable()->after('label');
            $table->foreignUlid('assigned_user_id')->nullable()->after('description')->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable()->after('assigned_user_id');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('todo_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn(['description', 'starts_at', 'ends_at']);
        });
        Schema::table('todos', fn (Blueprint $table) => $table->dropColumn('starts_at'));
    }
};
