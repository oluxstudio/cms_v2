<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estimator builder: admins define their OWN fields (visitor-entered or
 * fixed "set data") and basic calculations over them. Results are stored
 * on the estimate itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimator_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('key');                    // formula identifier, e.g. area_m2
            $table->string('label');
            $table->string('type')->default('number'); // number | select | toggle | text | fixed
            $table->json('options')->nullable();      // select: [{label, value}]
            $table->decimal('value', 12, 2)->nullable(); // fixed ("set data") value
            $table->string('unit', 20)->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['site_id', 'key']);
        });

        Schema::create('estimator_calcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('formula');                  // e.g. "area_m2 * rate + callout"
            $table->string('format')->default('money'); // money | number | hours
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->json('results')->nullable()->after('inputs'); // calc outputs
        });
    }

    public function down(): void
    {
        Schema::table('estimates', fn (Blueprint $t) => $t->dropColumn('results'));
        Schema::dropIfExists('estimator_calcs');
        Schema::dropIfExists('estimator_fields');
    }
};
