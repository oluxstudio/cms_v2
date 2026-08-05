<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('form_id')->constrained()->cascadeOnDelete();

            /**
             * All submitted field names and values stored as a flat JSON object.
             * Example: { "First Name": "Jane", "Email": "jane@example.com", "Message": "Hello!" }
             */
            $table->json('fields');

            $table->string('ip_address', 45)->nullable();

            /** NULL = unread, timestamped = read */
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index('form_id');
            $table->index(['form_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_responses');
    }
};
