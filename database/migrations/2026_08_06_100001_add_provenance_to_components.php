<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Component provenance: WHO created it (FK, not just the author name string)
 * and HOW — through the app interface or an API call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('author')->constrained('users')->nullOnDelete();
            $table->string('source', 10)->default('app')->after('created_by'); // app | api
        });
    }

    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('source');
        });
    }
};
