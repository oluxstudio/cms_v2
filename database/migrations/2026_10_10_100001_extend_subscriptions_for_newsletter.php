<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Newsletter module: the existing signup table becomes the subscriber list.
 * status: active|unsubscribed (enum) → pending|subscribed|unsubscribed|bounced
 * (string), plus a per-subscriber token for confirm / unsubscribe links,
 * confirmation + unsubscribe timestamps and free-form tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('status', 20)->default('subscribed')->change();
        });

        DB::table('subscriptions')->where('status', 'active')->update(['status' => 'subscribed']);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('status');
            $table->json('tags')->nullable()->after('source');
            $table->timestamp('confirmation_sent_at')->nullable()->after('tags');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_sent_at');
            $table->timestamp('unsubscribed_at')->nullable()->after('confirmed_at');
        });

        DB::table('subscriptions')->whereNull('token')->orderBy('id')->select('id', 'status', 'created_at')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('subscriptions')->where('id', $row->id)->update([
                        'token' => Str::random(40),
                        'confirmed_at' => $row->status === 'subscribed' ? $row->created_at : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn(['token', 'tags', 'confirmation_sent_at', 'confirmed_at', 'unsubscribed_at']);
        });

        DB::table('subscriptions')->where('status', 'subscribed')->update(['status' => 'active']);
        DB::table('subscriptions')->whereNotIn('status', ['active', 'unsubscribed'])->update(['status' => 'unsubscribed']);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', ['active', 'unsubscribed'])->default('active')->change();
        });
    }
};
