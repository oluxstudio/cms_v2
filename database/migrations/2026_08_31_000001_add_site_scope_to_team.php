<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Membership can now be scoped to ONE site (site_id set) or the whole
        // account (site_id null — every pre-existing row keeps meaning that).
        if (! Schema::hasColumn('account_members', 'site_id')) {
            Schema::table('account_members', function (Blueprint $table) {
                $table->foreignUlid('site_id')->nullable()->after('role_id')->constrained('sites')->cascadeOnDelete();
            });
            // Add the new composite first — the account_id FK needs a leftmost
            // account_id index to exist at all times (MySQL error 1553).
            Schema::table('account_members', fn (Blueprint $table) => $table->unique(['account_id', 'user_id', 'site_id']));
            Schema::table('account_members', fn (Blueprint $table) => $table->dropUnique(['account_id', 'user_id']));
        }
        if (! Schema::hasColumn('team_invitations', 'site_id')) {
            Schema::table('team_invitations', function (Blueprint $table) {
                $table->foreignUlid('site_id')->nullable()->after('role_id')->constrained('sites')->cascadeOnDelete();
            });
        }
        // Null = still on the temporary password an invite emailed them.
        // Stamped now() on every user-chosen password (signup, change, reset).
        if (! Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('password_changed_at')->nullable()->after('password');
            });
        }
    }

    public function down(): void
    {
        Schema::table('account_members', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'user_id', 'site_id']);
            $table->dropConstrainedForeignId('site_id');
            $table->unique(['account_id', 'user_id']);
        });
        Schema::table('team_invitations', fn (Blueprint $table) => $table->dropConstrainedForeignId('site_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_changed_at'));
    }
};
