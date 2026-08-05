<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices — bill customers, send by email with a hosted pay link (Stripe),
 * and track the money: draft → sent → paid (or overdue / cancelled).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('number', 20);                    // INV-0001 (per site)
            $t->string('public_token', 40)->unique();    // unguessable pay-page URL
            $t->string('customer_name');
            $t->string('customer_email');
            $t->json('items');                           // [{description, qty, unit_cents}]
            $t->unsignedInteger('subtotal_cents')->default(0);
            $t->unsignedSmallInteger('tax_bp')->default(0);   // basis points (850 = 8.5%)
            $t->unsignedInteger('tax_cents')->default(0);
            $t->unsignedInteger('total_cents')->default(0);
            $t->char('currency', 3)->default('usd');
            // draft | sent | paid | overdue | cancelled
            $t->string('status', 16)->default('draft');
            $t->date('due_date')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->string('stripe_session_id')->nullable()->index();
            $t->timestamps();
            $t->unique(['site_id', 'number']);
            $t->index(['site_id', 'status']);
            $t->index(['site_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::drop('invoices');
    }
};
