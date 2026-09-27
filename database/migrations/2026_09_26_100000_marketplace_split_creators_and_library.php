<?php

use App\Models\Template;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace split: templates get a CREATOR (template_creators, seeded with
 * "Olux Studio" owning every existing template) plus buyer-facing fields;
 * template_entitlements grow into the account's LIBRARY (purchase reference,
 * price paid, purchased_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_creators', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamps();
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('creator_id')->nullable()->after('user_id')->constrained('template_creators')->nullOnDelete();
            $table->string('short_description')->nullable()->after('description');
            $table->string('live_preview_url')->nullable()->after('thumbnail_url');
            $table->json('required_features')->nullable()->after('tags');
        });

        Schema::table('template_entitlements', function (Blueprint $table) {
            $table->integer('price_paid_cents')->nullable()->after('purchase_id');
            $table->string('stripe_session_id')->nullable()->after('price_paid_cents');
            $table->timestamp('purchased_at')->nullable()->after('stripe_session_id');
        });

        // Seed the house creator and attach every existing template to it.
        $creatorId = DB::table('template_creators')->insertGetId([
            'name' => 'Olux Studio', 'slug' => 'olux-studio',
            'bio' => 'First-party templates designed and maintained by the Olux team.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('templates')->whereNull('creator_id')->update(['creator_id' => $creatorId]);

        // Buyer-facing one-liner: first sentence of the description, minus dev notes.
        foreach (Template::whereNull('short_description')->get() as $t) {
            $short = trim((string) preg_replace('/\s*—?\s*replicates.*$/i', '', strtok((string) $t->description, '.')));
            $t->forceFill(['short_description' => $short !== '' ? $short.'.' : 'A ready-to-use site design.'])->saveQuietly();
        }
    }

    public function down(): void
    {
        Schema::table('template_entitlements', function (Blueprint $table) {
            $table->dropColumn(['price_paid_cents', 'stripe_session_id', 'purchased_at']);
        });
        Schema::table('templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creator_id');
            $table->dropColumn(['short_description', 'live_preview_url', 'required_features']);
        });
        Schema::dropIfExists('template_creators');
    }
};
