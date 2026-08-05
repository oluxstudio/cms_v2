<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified booking creation:
 *  · booking_types — admin-defined type presets ("Dentist visit", "Boat
 *    rental") built on one of the three availability engines (slot|stay|trip)
 *    with their own resource noun and default parameters.
 *  · deposits — services may require only a PART of the price online
 *    (fixed amount or %); bookings track what was actually paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('icon', 16)->default('📅');       // emoji
            $t->string('engine', 12)->default('slot');   // slot|stay|trip
            $t->string('resource_noun', 40)->nullable(); // staff member / room / boat…
            $t->json('defaults')->nullable();            // pre-filled service params
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
            $t->unique(['site_id', 'slug']);
        });

        Schema::table('services', function (Blueprint $t) {
            $t->foreignId('booking_type_id')->nullable()->after('kind')
              ->constrained('booking_types')->nullOnDelete();
            $t->unsignedInteger('deposit_cents')->nullable()->after('requires_payment');
            $t->unsignedSmallInteger('deposit_pct')->nullable()->after('deposit_cents'); // whole %
        });

        Schema::table('bookings', function (Blueprint $t) {
            $t->unsignedInteger('paid_cents')->default(0)->after('total_cents');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn('paid_cents'));
        Schema::table('services', function (Blueprint $t) {
            $t->dropConstrainedForeignId('booking_type_id');
            $t->dropColumn(['deposit_cents', 'deposit_pct']);
        });
        Schema::drop('booking_types');
    }
};
