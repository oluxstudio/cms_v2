<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('template_submissions', 'repo_url')) {
            Schema::table('template_submissions', function (Blueprint $table) {
                $table->string('repo_url')->nullable()->after('extraction');
                $table->string('repo_branch', 100)->nullable()->after('repo_url');
            });
        }
    }

    public function down(): void
    {
        Schema::table('template_submissions', fn (Blueprint $table) => $table->dropColumn(['repo_url', 'repo_branch']));
    }
};
