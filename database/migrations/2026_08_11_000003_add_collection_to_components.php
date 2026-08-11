<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            if (! Schema::hasColumn('components', 'collection_id')) {
                // A component may belong to one collection (which groups it with
                // sibling components), ordered by collection_order within it.
                $table->foreignUlid('collection_id')->nullable()->after('site_id')
                    ->constrained()->nullOnDelete();
                $table->unsignedInteger('collection_order')->nullable()->after('collection_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            if (Schema::hasColumn('components', 'collection_id')) {
                $table->dropConstrainedForeignId('collection_id');
                $table->dropColumn('collection_order');
            }
        });
    }
};
