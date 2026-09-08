<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'bookings_site_customer_email_index';

    public function up(): void
    {
        // Due-back clients and the rebooking rate group by customer_email on
        // every dashboard load — give them an index.
        if (! Schema::hasIndex('bookings', self::INDEX)) {
            Schema::table('bookings', fn (Blueprint $table) => $table->index(['site_id', 'customer_email'], self::INDEX));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('bookings', self::INDEX)) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropIndex(self::INDEX));
        }
    }
};
