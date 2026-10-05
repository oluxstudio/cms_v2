<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_uploads', function (Blueprint $table) {
            // Admin store uploads: public/private for the catalog template they become.
            $table->string('visibility', 16)->default('public')->after('for_store');
        });
    }

    public function down(): void
    {
        Schema::table('template_uploads', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
