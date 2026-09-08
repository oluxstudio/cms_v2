<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The marketplace sells in GBP (config templates.currency). Old column
     * defaults said 'usd' while every label/analytics formatter said gbp —
     * align the defaults and fix any FREE rows still stamped usd. Paid rows
     * (none exist yet) are deliberately left alone.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE templates ALTER currency SET DEFAULT 'gbp'");
        DB::statement("ALTER TABLE template_purchases ALTER currency SET DEFAULT 'gbp'");
        DB::table('templates')->where('currency', 'usd')->where('price_cents', 0)->update(['currency' => 'gbp']);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE templates ALTER currency SET DEFAULT 'usd'");
        DB::statement("ALTER TABLE template_purchases ALTER currency SET DEFAULT 'usd'");
    }
};
