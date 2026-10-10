<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Olux Local Referral Network: member profiles, referrals between sites (with
 * the customer's consent), their event history, and payouts to referrers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_profiles', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('site_id')->unique();
            $t->string('business_type', 60)->nullable();       // BlueprintRegistry type / free text
            $t->json('services')->nullable();                    // ["Rewiring", "EV chargers"]
            $t->string('area', 120)->nullable();                 // town / postcode area
            $t->string('postcode', 12)->nullable();
            $t->decimal('lat', 9, 6)->nullable();
            $t->decimal('lng', 9, 6)->nullable();
            $t->unsignedSmallInteger('radius_km')->default(25);
            $t->string('pitch', 500)->nullable();
            $t->unsignedInteger('fee_cents')->default(1500);     // per CONVERTED lead
            $t->string('currency', 3)->default('gbp');
            $t->boolean('accepting')->default(true);
            $t->timestamp('terms_accepted_at')->nullable();
            $t->string('terms_version', 40)->nullable();
            $t->timestamps();
            $t->index(['accepting', 'business_type']);
        });

        Schema::create('referrals', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('reference', 16)->unique();
            $t->ulid('from_site_id')->index();
            $t->ulid('to_site_id')->index();
            $t->ulid('from_contact_id')->nullable();             // the customer in the referrer's CRM
            $t->ulid('to_contact_id')->nullable();               // created in the receiver's CRM once shared
            $t->ulid('created_by')->nullable();                   // user who referred
            // The customer (copied so the consent email can be sent before sharing)
            $t->string('customer_name', 160);
            $t->string('customer_email', 190);
            $t->string('customer_phone', 40)->nullable();
            $t->text('note')->nullable();                         // referrer → receiver
            // Status machine — see App\Modules\Network\Models\Referral::STATUSES
            $t->string('status', 24)->default('pending_consent')->index();
            $t->unsignedInteger('fee_cents');                     // snapshot of the receiver's fee at referral time
            $t->string('currency', 3)->default('gbp');
            $t->decimal('olux_cut_pct', 5, 2);                    // snapshot of config at referral time
            // Consent (UK GDPR): what was agreed, when, how
            $t->string('consent_token_hash', 64)->nullable()->unique();
            $t->string('consent_method', 16)->nullable();         // email_link | form_tick
            $t->text('consent_text')->nullable();
            $t->timestamp('consent_requested_at')->nullable();
            $t->timestamp('consented_at')->nullable();
            $t->string('consent_ip', 45)->nullable();
            // Lifecycle timestamps
            $t->timestamp('shared_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('declined_at')->nullable();
            $t->string('decline_reason', 300)->nullable();
            $t->timestamp('converted_at')->nullable();
            $t->string('converted_via', 40)->nullable();          // booking:{id} | invoice:{id} | order:{id} | manual
            $t->timestamp('disputed_at')->nullable();
            $t->string('dispute_reason', 500)->nullable();
            $t->timestamp('dispute_resolved_at')->nullable();
            $t->string('dispute_outcome', 16)->nullable();        // upheld (no fee) | rejected (fee stands)
            $t->timestamp('billed_at')->nullable();
            $t->string('stripe_invoice_item_id', 80)->nullable(); // line on the receiver's Olux bill
            $t->timestamp('collected_at')->nullable();
            $t->timestamp('expires_at')->nullable();              // conversion window end
            $t->timestamps();
            $t->index(['to_site_id', 'status']);
            $t->index(['from_site_id', 'status']);
            $t->index(['customer_email', 'to_site_id']);
        });

        Schema::create('referral_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('referral_id')->index();
            $t->string('type', 32);                               // created, consent_requested, consented, shared, accepted, …
            $t->ulid('user_id')->nullable();
            $t->json('data')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('referral_payouts', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('referral_id')->unique();
            $t->ulid('site_id')->index();                         // the referrer (who gets paid)
            $t->unsignedInteger('gross_cents');                   // the referral fee
            $t->unsignedInteger('olux_cents');                    // Olux's cut
            $t->unsignedInteger('net_cents');                     // paid to the referrer
            $t->string('currency', 3)->default('gbp');
            // pending (fee not collected yet) → ready (collected, awaiting payout) → transferred | credited | failed
            $t->string('status', 16)->default('pending')->index();
            $t->string('method', 16)->nullable();                 // connect_transfer | olux_credit
            $t->string('stripe_transfer_id', 80)->nullable();
            $t->string('stripe_balance_txn_id', 80)->nullable();  // customer-balance credit
            $t->string('failure', 500)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_payouts');
        Schema::dropIfExists('referral_events');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('network_profiles');
    }
};
