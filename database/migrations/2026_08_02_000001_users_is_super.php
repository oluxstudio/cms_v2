<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Super-admin flag: sees and accesses every site + module in the CMS. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_super')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('is_super')->default(false)->after('email'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_super')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_super'));
        }
    }
};
