<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sales outlive their template: removing a template from the platform keeps
 * its purchase rows (accounting) with the template's name, instead of
 * cascading them away.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded so a half-applied run (MySQL DDL isn't transactional) can resume.
        if (! Schema::hasColumn('template_purchases', 'template_name')) {
            Schema::table('template_purchases', fn (Blueprint $table) => $table->string('template_name')->nullable()->after('template_id'));
        }
        if (collect(Schema::getForeignKeys('template_purchases'))->contains(fn ($fk) => $fk['columns'] === ['template_id'])) {
            Schema::table('template_purchases', fn (Blueprint $table) => $table->dropForeign(['template_id']));
        }
        Schema::table('template_purchases', function (Blueprint $table) {
            $table->char('template_id', 26)->nullable()->change(); // templates.id is a ULID
            $table->foreign('template_id')->references('id')->on('templates')->nullOnDelete();
        });
        DB::table('template_purchases')->whereNull('template_name')->update([
            'template_name' => DB::raw('(select name from templates where templates.id = template_purchases.template_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('template_purchases', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_name');
        });
        Schema::table('template_purchases', function (Blueprint $table) {
            $table->foreign('template_id')->references('id')->on('templates')->cascadeOnDelete();
        });
    }
};
