<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 14)->unique();
            $table->string('trade', 40);
            $table->string('customer_name', 120);
            $table->string('customer_email', 160);
            $table->string('customer_phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->json('inputs');                 // the parameters the visitor chose
            $table->unsignedInteger('cost_low_cents');
            $table->unsignedInteger('cost_high_cents');
            $table->string('currency', 3)->default('gbp');
            $table->decimal('hours', 6, 1);
            $table->string('completion', 60);
            $table->string('status', 20)->default('new');   // new | contacted | won | lost
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
