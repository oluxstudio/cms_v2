<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mailboxes an account already had when its plan's allowance shrank (the
 * 2026 plan line-up: Starter 5 → forwarding only, Pro 20 → 10). They keep
 * working: AccountSubscription::mailboxLimitOn() never goes below this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('grandfathered_mailboxes')->nullable()->after('extra_mailboxes');
        });
    }

    public function down(): void
    {
        Schema::table('account_subscriptions', function (Blueprint $table) {
            $table->dropColumn('grandfathered_mailboxes');
        });
    }
};
