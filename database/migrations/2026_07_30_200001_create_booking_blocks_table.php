<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Availability exceptions for slot bookings: block a WHOLE day
 * (start_time NULL — holiday/closure) or a single slot (start_time set —
 * lunch break, personal appointment). Site-wide; checked by the engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->time('start_time')->nullable(); // NULL = the whole day is off
            $t->timestamps();
            $t->unique(['site_id', 'date', 'start_time']);
            $t->index(['site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::drop('booking_blocks');
    }
};
