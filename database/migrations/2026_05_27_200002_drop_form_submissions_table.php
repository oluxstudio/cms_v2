<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The old flat form_submissions table (site_id + form_name + fields JSON) has been
 * replaced by the normalised forms + form_responses structure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('form_submissions');
    }

    public function down(): void
    {
        // Intentionally not re-created — use the original migration to roll back further.
    }
};
