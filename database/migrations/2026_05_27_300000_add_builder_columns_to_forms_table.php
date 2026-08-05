<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');

            /**
             * Array of field-definition objects, e.g.:
             * [
             *   { "key":"email","label":"Email","type":"email",
             *     "required":true,"placeholder":"you@…","min":null,"max":"255","options":[] }
             * ]
             */
            $table->json('fields')->nullable()->after('description');

            /** When false the API rejects new submissions. */
            $table->boolean('is_active')->default(true)->after('fields');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['description', 'fields', 'is_active']);
        });
    }
};
