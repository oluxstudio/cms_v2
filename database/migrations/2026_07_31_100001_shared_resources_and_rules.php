<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Booking Engine v2 — Resource + Slot + Rules:
 *  · resources become SITE-LEVEL and shared across services (many-to-many);
 *    a stylist booked for a haircut is busy for coloring too.
 *  · bookings carry explicit BUSY WINDOWS (service buffers baked in) so
 *    cross-service conflicts are one indexed range query.
 *  · price_rules: seasonal/date-range pricing at service or resource level.
 *
 * Guarded per-step (MySQL DDL is non-transactional).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_resources', 'site_id')) {
            Schema::table('service_resources', function (Blueprint $t) {
                $t->foreignId('site_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                $t->unsignedInteger('capacity')->default(1)->after('config');     // meeting room holds 12
                $t->unsignedInteger('price_cents')->nullable()->after('capacity'); // per-resource price override
            });
            DB::statement('UPDATE service_resources sr JOIN services s ON s.id = sr.service_id SET sr.site_id = s.site_id');
        }

        if (! Schema::hasTable('resource_service')) {
            Schema::create('resource_service', function (Blueprint $t) {
                $t->foreignId('resource_id')->constrained('service_resources')->cascadeOnDelete();
                $t->foreignId('service_id')->constrained()->cascadeOnDelete();
                $t->unique(['resource_id', 'service_id']);
            });
            DB::statement('INSERT INTO resource_service (resource_id, service_id) SELECT id, service_id FROM service_resources WHERE service_id IS NOT NULL');
        }

        if (Schema::hasColumn('service_resources', 'service_id')) {
            Schema::table('service_resources', function (Blueprint $t) {
                $t->dropConstrainedForeignId('service_id');
            });
        }

        if (! Schema::hasColumn('bookings', 'busy_from')) {
            Schema::table('bookings', function (Blueprint $t) {
                $t->dateTime('busy_from')->nullable()->after('ends_at');
                $t->dateTime('busy_until')->nullable()->after('busy_from');
                $t->index(['resource_id', 'status', 'busy_from', 'busy_until'], 'bookings_busy_idx');
            });
            DB::statement('UPDATE bookings SET busy_from = starts_at, busy_until = ends_at');
        }

        if (! Schema::hasTable('price_rules')) {
            Schema::create('price_rules', function (Blueprint $t) {
                $t->id();
                $t->foreignId('site_id')->constrained()->cascadeOnDelete();
                $t->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
                $t->foreignId('resource_id')->nullable()->constrained('service_resources')->cascadeOnDelete();
                $t->date('starts_on');
                $t->date('ends_on');
                $t->unsignedInteger('price_cents');
                $t->string('label', 80)->nullable(); // "High season", "Weekend"
                $t->timestamps();
                $t->index(['site_id', 'service_id', 'starts_on', 'ends_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rules');
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropIndex('bookings_busy_idx');
            $t->dropColumn(['busy_from', 'busy_until']);
        });
        Schema::table('service_resources', function (Blueprint $t) {
            $t->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
        });
        DB::statement('UPDATE service_resources sr JOIN resource_service rs ON rs.resource_id = sr.id SET sr.service_id = rs.service_id');
        Schema::drop('resource_service');
        Schema::table('service_resources', function (Blueprint $t) {
            $t->dropConstrainedForeignId('site_id');
            $t->dropColumn(['capacity', 'price_cents']);
        });
    }
};
