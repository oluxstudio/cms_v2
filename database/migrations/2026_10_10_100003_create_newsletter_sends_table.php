<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per campaign recipient: delivery state + open / click / unsubscribe tracking. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_sends', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('campaign_id', 26);
            $table->char('site_id', 26);
            $table->string('subscription_id', 26)->nullable();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('queued'); // queued|sending|sent|failed|skipped
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('newsletter_campaigns')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['campaign_id', 'status']);
            $table->index(['site_id', 'created_at']);
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_sends');
    }
};
