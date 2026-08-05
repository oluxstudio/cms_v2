<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // actor; null = system
            // created | form_submitted | status_change | assigned | note
            $table->string('type');
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'created_at']);
        });

        // Backfill: a "created" activity for every existing contact, plus a
        // "form_submitted" entry for each linked response, so timelines aren't empty.
        DB::table('contacts')->orderBy('id')->each(function ($contact) {
            DB::table('activities')->insert([
                'contact_id' => $contact->id,
                'user_id'    => null,
                'type'       => 'created',
                'body'       => null,
                'meta'       => null,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->created_at,
            ]);

            DB::table('form_responses')
                ->where('contact_id', $contact->id)
                ->orderBy('created_at')
                ->get()
                ->each(function ($r) use ($contact) {
                    DB::table('activities')->insert([
                        'contact_id' => $contact->id,
                        'user_id'    => null,
                        'type'       => 'form_submitted',
                        'body'       => null,
                        'meta'       => json_encode(['response_id' => $r->id, 'form_id' => $r->form_id]),
                        'created_at' => $r->created_at,
                        'updated_at' => $r->created_at,
                    ]);
                });
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
