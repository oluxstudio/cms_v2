<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account-level RBAC: clients invite users into THEIR account (email-verified
 * invitations), assign them roles, and roles carry granular permissions from
 * the config/permissions.php catalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Roles are scoped to a client account (the owning user).
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->json('permissions')->nullable(); // list of catalog keys, or ['*']
            $table->boolean('is_system')->default(false); // seeded defaults — editable, not deletable
            $table->timestamps();
            $table->unique(['account_id', 'slug']);
        });

        // Membership: user belongs to a client account with exactly one role.
        Schema::create('account_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
        });

        // Pending invitations — the token is stored HASHED; the plain token
        // only ever exists inside the invitation email.
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique(); // sha256 of the mailed token
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('account_members');
        Schema::dropIfExists('roles');
    }
};
