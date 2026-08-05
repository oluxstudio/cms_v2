<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('donor_email')->nullable();
            $table->string('donor_name')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('usd');
            $table->text('message')->nullable();
            $table->string('status')->default('pending'); // pending | paid
            $table->string('stripe_session_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
