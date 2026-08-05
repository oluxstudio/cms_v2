<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalize the booking engine to THREE archetypes (services.kind):
 *   slot — barber/salon/mechanic time slots (the original behavior)
 *   stay — rooms/hotels/houses: date-range bookings against N identical units
 *   trip — car/bus transport: scheduled departures with seat capacity
 *
 * appointments → bookings: kind-specific params live in JSON, quantities and
 * money are first-class, and the old [site,service,starts_at] unique index is
 * replaced by transactional row locks (capacity > 1 and ranges break it).
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is non-transactional — every step below is guarded so a
        // failed run can resume instead of tripping over its own progress.
        if (! Schema::hasColumn('services', 'kind')) {
            Schema::table('services', function (Blueprint $t) {
                $t->string('kind', 12)->default('slot')->after('slug'); // slot|stay|trip
                $t->json('config')->nullable()->after('kind');          // per-kind params
                $t->boolean('requires_payment')->default(false)->after('price_cents');
                // stay: identical units of this room type · slot: parallel chairs
                $t->unsignedInteger('capacity')->default(1)->after('requires_payment');
                $t->index(['site_id', 'kind']);
            });
        }

        if (! Schema::hasTable('service_departures')) Schema::create('service_departures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->string('origin');
            $t->string('destination');
            $t->dateTime('departs_at');
            $t->unsignedInteger('seats');
            $t->unsignedInteger('price_cents')->nullable(); // null = service price
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['service_id', 'departs_at']);
        });

        if (Schema::hasTable('appointments')) {
            Schema::rename('appointments', 'bookings');
        }

        if (Schema::hasColumn('bookings', 'reference')) {
            return; // already completed
        }

        Schema::table('bookings', function (Blueprint $t) {
            // The unique index keeps its PRE-RENAME name.
            $t->dropUnique('appointments_site_id_service_id_starts_at_unique');
            $t->string('reference', 12)->nullable()->after('id');
            $t->foreignId('departure_id')->nullable()->after('service_id')
              ->constrained('service_departures')->nullOnDelete();
            $t->json('params')->nullable();          // kind payload (check_in/out, guests, qty…)
            $t->unsignedInteger('quantity')->default(1);
            $t->unsignedInteger('total_cents')->default(0);
            $t->char('currency', 3)->default('usd');
            $t->string('stripe_session_id')->nullable()->index();
            $t->unique(['site_id', 'reference']);
            $t->index(['site_id', 'service_id', 'status', 'starts_at', 'ends_at'], 'bookings_overlap_idx');
            $t->index(['departure_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropUnique(['site_id', 'reference']);
            $t->dropIndex('bookings_overlap_idx');
            $t->dropIndex(['departure_id', 'status']);
            $t->dropConstrainedForeignId('departure_id');
            $t->dropColumn(['reference', 'params', 'quantity', 'total_cents', 'currency', 'stripe_session_id']);
            $t->unique(['site_id', 'service_id', 'starts_at']);
        });
        Schema::rename('bookings', 'appointments');

        Schema::drop('service_departures');

        Schema::table('services', function (Blueprint $t) {
            $t->dropIndex(['site_id', 'kind']);
            $t->dropColumn(['kind', 'config', 'requires_payment', 'capacity']);
        });
    }
};
