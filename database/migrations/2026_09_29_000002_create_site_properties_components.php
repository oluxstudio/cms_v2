<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /** Every site gets its "Site Properties" component (earlier site.* values moved in). */
    public function up(): void
    {
        Artisan::call('site-properties:migrate');
    }

    public function down(): void
    {
        // Components hold owner data — never removed automatically.
    }
};
