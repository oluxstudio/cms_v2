<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Domain orders used to be saved as `pending` the moment the buyer was sent
 * to Stripe Checkout, before anything was paid — so an abandoned checkout
 * showed as "Payment received — registering". Unpaid orders are now
 * `checkout`; `paid` alone means "paid, being registered". A checkout that
 * did complete is still fulfilled by the webhook / success return, which
 * accept `checkout` orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('domain_orders')->where('status', 'pending')->update(['status' => 'checkout']);
    }

    public function down(): void
    {
        // Irreversible in meaning (both were "not paid yet"); nothing to undo.
    }
};
