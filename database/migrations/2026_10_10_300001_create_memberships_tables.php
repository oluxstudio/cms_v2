<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memberships module: tiers (free or recurring paid), members, hashed
 * magic-link / member-session tokens, a per-member history, and the
 * members-only content map (no columns are added to posts/pages/collections).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->json('benefits')->nullable();
            $table->unsignedInteger('price_cents')->default(0);    // 0 = free
            $table->string('interval', 8)->default('month');        // month | year
            $table->string('currency', 3)->default('gbp');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['site_id', 'slug']);
        });

        Schema::create('members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('tier_id')->nullable()->index();
            $table->string('name', 160);
            $table->string('email', 190);
            $table->string('status', 12)->default('pending');       // active | pending | past_due | cancelled
            // Price snapshot at join time (tiers can be re-priced later).
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('interval', 8)->default('month');
            $table->string('currency', 3)->default('gbp');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            // Ids on the site's CONNECTED Stripe account.
            $table->string('stripe_customer_id', 80)->nullable()->index();
            $table->string('stripe_subscription_id', 80)->nullable()->index();
            $table->string('stripe_checkout_id', 120)->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'email']);
            $table->index(['site_id', 'status']);
        });

        Schema::create('member_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('member_id')->index();
            $table->string('kind', 10);                             // magic | session
            $table->string('token_hash', 64)->unique();             // sha256 of the raw token
            $table->string('return_url', 500)->nullable();
            $table->string('next', 20)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('membership_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->foreignUlid('member_id')->index();
            $table->string('type', 32);
            $table->string('detail', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('membership_access', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->index();
            $table->string('content_type', 12);                     // post | page | collection
            $table->string('content_id', 40);
            $table->json('tier_ids')->nullable();                   // empty = any active member
            $table->timestamps();
            $table->unique(['site_id', 'content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_access');
        Schema::dropIfExists('membership_events');
        Schema::dropIfExists('member_tokens');
        Schema::dropIfExists('members');
        Schema::dropIfExists('membership_tiers');
    }
};
