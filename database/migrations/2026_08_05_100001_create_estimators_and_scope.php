<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Named estimators: a site can run MANY estimators (Cleaner, Mover, …), each
 * owning its fields, calculations and its own customer email template.
 * Existing site-level fields/calcs are folded into a "General" estimator.
 * Steps are guarded so a partially-applied run (MySQL DDL is not
 * transactional) can be resumed safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estimators')) {
            Schema::create('estimators', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('email_subject')->nullable();
                $table->text('email_body')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
                $table->unique(['site_id', 'slug']);
            });
        }

        if (! Schema::hasColumn('estimator_fields', 'estimator_id')) {
            Schema::table('estimator_fields', function (Blueprint $table) {
                $table->foreignId('estimator_id')->nullable()->after('site_id')->constrained()->cascadeOnDelete();
            });
        }
        if (! Schema::hasColumn('estimator_calcs', 'estimator_id')) {
            Schema::table('estimator_calcs', function (Blueprint $table) {
                $table->foreignId('estimator_id')->nullable()->after('site_id')->constrained()->cascadeOnDelete();
            });
        }
        if (! Schema::hasColumn('estimates', 'estimator_id')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->foreignId('estimator_id')->nullable()->after('site_id')->constrained()->nullOnDelete();
            });
        }

        // Fold any pre-estimator fields/calcs into a "General" estimator per site.
        $siteIds = DB::table('estimator_fields')->whereNull('estimator_id')->pluck('site_id')
            ->merge(DB::table('estimator_calcs')->whereNull('estimator_id')->pluck('site_id'))->unique();
        foreach ($siteIds as $siteId) {
            $id = DB::table('estimators')->where('site_id', $siteId)->where('slug', 'general')->value('id')
                ?? DB::table('estimators')->insertGetId([
                    'site_id' => $siteId, 'name' => 'General', 'slug' => 'general',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('estimator_fields')->where('site_id', $siteId)->whereNull('estimator_id')->update(['estimator_id' => $id]);
            DB::table('estimator_calcs')->where('site_id', $siteId)->whereNull('estimator_id')->update(['estimator_id' => $id]);
        }

        // Field keys are unique WITHIN an estimator now, not per site. The
        // site_id FK needs its own index before the composite unique goes.
        $indexes = collect(DB::select('SHOW INDEX FROM estimator_fields'))->pluck('Key_name');
        Schema::table('estimator_fields', function (Blueprint $table) use ($indexes) {
            if (! $indexes->contains('estimator_fields_site_id_index')) {
                $table->index('site_id');
            }
        });
        Schema::table('estimator_fields', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('estimator_fields_site_id_key_unique')) {
                $table->dropUnique(['site_id', 'key']);
            }
            if (! $indexes->contains('estimator_fields_estimator_id_key_unique')) {
                $table->unique(['estimator_id', 'key']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimator_fields', function (Blueprint $table) {
            $table->dropUnique(['estimator_id', 'key']);
            $table->unique(['site_id', 'key']);
            $table->dropIndex(['site_id']);
            $table->dropConstrainedForeignId('estimator_id');
        });
        Schema::table('estimator_calcs', fn (Blueprint $t) => $t->dropConstrainedForeignId('estimator_id'));
        Schema::table('estimates', fn (Blueprint $t) => $t->dropConstrainedForeignId('estimator_id'));
        Schema::dropIfExists('estimators');
    }
};
