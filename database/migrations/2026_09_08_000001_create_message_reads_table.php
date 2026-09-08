<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-user read receipts — a broadcast is "unread" per member, not globally.
        Schema::create('message_reads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id');
            $table->foreignUlid('user_id');
            $table->timestamp('read_at')->nullable();
            $table->unique(['message_id', 'user_id']);
        });

        // Messaging gets its own permissions; nobody who could read the inbox
        // (contacts.view) loses it — existing roles are topped up in place.
        foreach (DB::table('roles')->get(['id', 'permissions']) as $role) {
            $perms = json_decode($role->permissions ?? '[]', true) ?: [];
            if (in_array('*', $perms, true)) {
                continue;
            }
            if (in_array('contacts.view', $perms, true) && ! in_array('messages.view', $perms, true)) {
                $perms[] = 'messages.view';
                $perms[] = 'messages.send';
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($perms))]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reads');
    }
};
