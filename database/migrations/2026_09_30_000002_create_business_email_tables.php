<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business email: mailboxes on a tenant's own domain (Openprovider Business
 * Email). Tenant = the owning account (users.id). Passwords are never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('source', 16);                       // openprovider | byo
            $table->string('provider_reference')->nullable();   // provider's id for the email domain
            $table->string('status', 16)->default('pending_dns'); // pending_dns | verifying | active | suspended | failed
            $table->timestamp('mx_change_confirmed_at')->nullable();
            $table->json('dns_report')->nullable();             // last check: record → ok/missing/found values
            $table->unsignedInteger('dns_attempts')->default(0);
            $table->timestamp('dns_checked_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('delete_after')->nullable();      // end of the export window after cancellation
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });

        Schema::create('mailboxes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('email_domain_id')->constrained('email_domains')->cascadeOnDelete();
            $table->string('local_part', 64);
            $table->string('display_name', 120)->nullable();
            $table->string('provider_reference')->nullable();   // Openprovider mailcow order id
            $table->unsignedSmallInteger('quota_gb')->default(15);
            $table->unsignedBigInteger('quota_used_mb')->nullable();
            $table->string('status', 16)->default('pending');   // pending | active | failed | suspended | deleting
            $table->string('idempotency_key', 64)->unique();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['email_domain_id', 'local_part']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('mailbox_aliases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('mailbox_id')->constrained('mailboxes')->cascadeOnDelete();
            $table->foreignUlid('email_domain_id')->constrained('email_domains')->cascadeOnDelete();
            $table->string('local_part', 64);
            $table->string('provider_reference')->nullable();   // provider alias id
            $table->string('status', 16)->default('pending');   // pending | active | failed | deleting
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['email_domain_id', 'local_part']);
        });

        Schema::create('email_provider_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('action', 64);
            $table->string('method', 8);
            $table->string('path');
            $table->json('request')->nullable();                // passwords/tokens redacted
            $table->json('response')->nullable();               // passwords/tokens redacted
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['account_id', 'created_at']);
        });

        Schema::table('account_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('mailbox_limit_override')->nullable()->after('price_overrides'); // Enterprise: set by platform admins
            $table->unsignedInteger('extra_mailboxes')->default(0)->after('mailbox_limit_override'); // purchased add-ons
            $table->string('mailbox_addon_item_id')->nullable()->after('extra_mailboxes');          // Stripe subscription item
        });
    }

    public function down(): void
    {
        Schema::table('account_subscriptions', fn (Blueprint $t) => $t->dropColumn(['mailbox_limit_override', 'extra_mailboxes', 'mailbox_addon_item_id']));
        Schema::dropIfExists('email_provider_logs');
        Schema::dropIfExists('mailbox_aliases');
        Schema::dropIfExists('mailboxes');
        Schema::dropIfExists('email_domains');
    }
};
