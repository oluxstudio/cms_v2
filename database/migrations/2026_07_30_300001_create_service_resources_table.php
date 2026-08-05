<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Named bookable RESOURCES within a service — each with its own availability:
 *   slot service → staff members (own schedule overrides)
 *   stay service → individual rooms/houses (own overlap calendar)
 *   trip service → vehicles (assigned to departures)
 * When a service has active resources they replace the anonymous
 * capacity/units count; without resources the legacy capacity still applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_resources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->json('config')->nullable();   // staff: {days, open_time, close_time}
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
            $t->index(['service_id', 'is_active']);
        });

        Schema::table('bookings', function (Blueprint $t) {
            $t->foreignId('resource_id')->nullable()->after('departure_id')
              ->constrained('service_resources')->nullOnDelete();
            $t->index(['resource_id', 'status']);
        });

        Schema::table('service_departures', function (Blueprint $t) {
            // The vehicle operating this departure (optional).
            $t->foreignId('resource_id')->nullable()->after('service_id')
              ->constrained('service_resources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_departures', fn (Blueprint $t) => $t->dropConstrainedForeignId('resource_id'));
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropIndex(['resource_id', 'status']);
            $t->dropConstrainedForeignId('resource_id');
        });
        Schema::drop('service_resources');
    }
};
