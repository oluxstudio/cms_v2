<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per payout to a creator (Stripe transfer or recorded manually).
        Schema::create('creator_payouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('creator_user_id', 26)->index();
            $table->integer('amount_cents');
            $table->char('currency', 3)->default('gbp');
            $table->string('status', 12)->default('pending'); // pending | paid | failed
            $table->string('method', 12)->default('manual');  // stripe | manual
            $table->string('stripe_transfer_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('created_by', 26)->nullable();
            $table->timestamps();
        });

        Schema::table('template_purchases', function (Blueprint $table) {
            // Who earns the creator share (null = platform-owned template).
            $table->string('creator_user_id', 26)->nullable()->index()->after('user_id');
            // Which payout settled this sale; a refund after payout is clawed
            // back from the creator's next payout.
            $table->string('payout_id', 26)->nullable()->index()->after('status');
            $table->string('clawback_payout_id', 26)->nullable()->after('payout_id');
            $table->timestamp('refunded_at')->nullable()->after('purchased_at');
        });

        // The store checkout used to record sales as "completed", which every
        // report skipped (they count "paid").
        DB::table('template_purchases')->where('status', 'completed')->update(['status' => 'paid']);
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_payouts');
        Schema::table('template_purchases', function (Blueprint $table) {
            $table->dropColumn(['creator_user_id', 'payout_id', 'clawback_payout_id', 'refunded_at']);
        });
    }
};
