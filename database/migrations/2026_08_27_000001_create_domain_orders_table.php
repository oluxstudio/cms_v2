<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('site_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->unsignedSmallInteger('years')->default(1);
            $table->unsignedInteger('price_cents');
            $table->string('plan')->nullable()->comment('Hosting plan bought in the same checkout');
            $table->string('status')->default('pending')->comment('pending|paid|registered|failed');
            $table->string('stripe_session_id')->nullable()->index();
            $table->string('registrar_ref')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_orders');
    }
};
