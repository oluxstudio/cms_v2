<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('site_id', 26)->index();
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->longText('body')->nullable();
            // null = every subscribed address; otherwise only subscribers carrying this tag.
            $table->string('audience_tag', 60)->nullable();
            $table->string('status', 20)->default('draft'); // draft|scheduled|sending|sent
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('opens_count')->default(0);   // unique opens
            $table->unsignedInteger('clicks_count')->default(0);  // unique clickers
            $table->unsignedInteger('unsubscribes_count')->default(0);
            $table->string('error')->nullable();
            $table->string('created_by', 26)->nullable();
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
