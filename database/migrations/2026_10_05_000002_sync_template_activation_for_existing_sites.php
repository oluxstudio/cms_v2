<?php

use App\Models\Site;
use App\Services\TemplateInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Existing sites: tag the pages / forms / sections each of their installed
 * templates declares and park those that don't belong to the current one
 * (TemplateInstaller::syncActivation). Nothing is deleted; switching back to
 * a template — or "Activate" in the admin — brings its items back.
 */
return new class extends Migration
{
    public function up(): void
    {
        $installer = app(TemplateInstaller::class);
        Site::whereNotNull('template')->each(function (Site $site) use ($installer) {
            try {
                $installer->syncActivation($site);
            } catch (\Throwable $e) {
                report($e); // one odd site must not stop the rest
            }
        });
    }

    public function down(): void
    {
        // Everything active again (the tags stay; they only mark ownership).
        DB::table('pages')->update(['template_active' => true]);
        DB::table('forms')->update(['template_active' => true]);
        DB::table('page_component')->update(['active' => true]);
    }
};
