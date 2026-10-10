<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events & Tickets module (App\Modules\Events): events, their ticket types,
 * ticket orders (free RSVPs and paid Stripe Connect checkouts) and the
 * individual tickets / attendees issued against an order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('site_id', 26)->index();
            $table->string('title');
            $table->string('slug');
            $table->string('summary', 500)->nullable();
            $table->longText('description')->nullable();
            $table->string('image')->nullable();            // "@media/…" ref or URL
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('venue_name')->nullable();
            $table->string('venue_address', 500)->nullable();
            $table->string('online_url', 500)->nullable();
            $table->unsignedInteger('capacity')->nullable();  // null = unlimited
            $table->string('status', 16)->default('draft');   // draft | published | cancelled
            $table->json('settings')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'status', 'starts_at']);
        });

        Schema::create('event_ticket_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('event_id', 26)->index();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('price_cents')->default(0); // 0 = free RSVP
            $table->unsignedInteger('quantity')->nullable();    // null = unlimited
            $table->dateTime('sales_start')->nullable();
            $table->dateTime('sales_end')->nullable();
            $table->unsignedSmallInteger('max_per_order')->default(10);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('event_ticket_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('site_id', 26)->index();
            $table->char('event_id', 26)->index();
            $table->string('reference', 16)->unique();
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_phone', 40)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('total_cents')->default(0);
            $table->char('currency', 3)->default('gbp');
            $table->string('status', 16)->default('pending'); // pending | paid | cancelled | refunded
            $table->string('checkout_session_id')->nullable()->index();
            $table->string('payment_ref')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['site_id', 'status', 'paid_at']);
        });

        Schema::create('event_tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('order_id', 26)->index();
            $table->char('event_id', 26)->index();
            $table->char('ticket_type_id', 26)->index();
            $table->string('attendee_name');
            $table->string('attendee_email')->nullable();
            $table->string('code', 16)->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tickets');
        Schema::dropIfExists('event_ticket_orders');
        Schema::dropIfExists('event_ticket_types');
        Schema::dropIfExists('site_events');
    }
};
