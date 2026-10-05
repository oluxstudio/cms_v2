<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Template visibility: public (store / gallery) or private (only the accounts
 * it's assigned to, via a library entitlement). Account uploads were already
 * "private" by status — they become private by visibility too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('visibility', 16)->default('public')->after('status');
            $table->index(['visibility', 'status']);
        });
        DB::table('templates')->where('status', 'private')->orWhere('source', 'upload')->update(['visibility' => 'private']);

        Schema::table('template_entitlements', function (Blueprint $table) {
            $table->foreignUlid('granted_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('template_entitlements', fn (Blueprint $t) => $t->dropConstrainedForeignId('granted_by'));
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'status']);
            $table->dropColumn('visibility');
        });
    }
};
