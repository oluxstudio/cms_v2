<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Site-level currency (default POUND) driving all money display + checkout. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sites', 'currency')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->string('currency', 8)->default('gbp')->after('theme');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sites', 'currency')) {
            Schema::table('sites', fn (Blueprint $t) => $t->dropColumn('currency'));
        }
    }
};
