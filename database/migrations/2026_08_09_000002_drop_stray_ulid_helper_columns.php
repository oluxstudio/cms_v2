<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ULID conversion left its `__ulid` helper column behind on every
 * converted table (the real `id` is already char(26); `__ulid` is dead
 * duplicate data that can leak into toArray()). Drop it everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $tables = DB::select("
            SELECT TABLE_NAME t FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = '__ulid'
        ");

        foreach ($tables as $row) {
            if (Schema::hasColumn($row->t, '__ulid')) {
                DB::statement("ALTER TABLE `{$row->t}` DROP COLUMN `__ulid`");
            }
        }
    }

    public function down(): void
    {
        // Nothing to restore — the column was disposable scaffolding.
    }
};
