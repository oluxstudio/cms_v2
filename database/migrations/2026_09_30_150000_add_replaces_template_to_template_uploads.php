<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** An upload that ships a NEW VERSION of an existing store template (same key). */
    public function up(): void
    {
        Schema::table('template_uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('template_uploads', 'replaces_template_id')) {
                $table->foreignUlid('replaces_template_id')->nullable()->after('template_id')
                    ->constrained('templates')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('template_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('template_uploads', 'replaces_template_id')) {
                $table->dropConstrainedForeignId('replaces_template_id');
            }
        });
    }
};
