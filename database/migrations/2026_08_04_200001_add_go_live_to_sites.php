<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Go-live state: a site becomes servable on its custom domain. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('live')->default(false)->after('domain');
            $table->timestamp('domain_verified_at')->nullable()->after('live');
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex(['domain']);
            $table->dropColumn(['live', 'domain_verified_at']);
        });
    }
};
