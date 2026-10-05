<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account-level task alerts: background work (template builds, design
 * installs, business email…) tells the person who started it when it's done.
 * Alerts without a site belong to the account (user_id); toasted_at marks the
 * ones already shown as a toast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('site_id', 26)->nullable()->change();
            $table->timestamp('toasted_at')->nullable()->after('read_at');
            $table->index(['user_id', 'toasted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'toasted_at']);
            $table->dropColumn('toasted_at');
        });
    }
};
