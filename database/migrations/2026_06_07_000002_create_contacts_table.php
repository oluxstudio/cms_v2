<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            // new | contacted | qualified | won | lost
            $table->string('status')->default('new');
            $table->foreignId('source_form_id')->nullable()->constrained('forms')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data')->nullable();            // any extra submitted fields
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'email']);
        });

        // Link form responses to the contact they created / updated
        Schema::table('form_responses', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('form_id')
                  ->constrained('contacts')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('form_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->dropColumn('converted_at');
        });

        Schema::dropIfExists('contacts');
    }
};
