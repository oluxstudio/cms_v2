<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            if (! Schema::hasColumn('forms', 'email_template')) {
                // { customized: bool, subject: string, sections: [...] }
                // null / customized=false → the form uses the site-wide default.
                $table->json('email_template')->nullable()->after('delivery');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            if (Schema::hasColumn('forms', 'email_template')) {
                $table->dropColumn('email_template');
            }
        });
    }
};
