<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('avatar');
            $table->string('department')->nullable()->after('job_title');
            $table->string('timezone')->default('UTC')->after('department');
            $table->string('language')->default('en')->after('timezone');
            $table->string('date_format')->default('MM/DD/YYYY')->after('language');
            $table->string('theme')->default('light')->after('date_format');
            $table->boolean('notif_email')->default(true)->after('theme');
            $table->boolean('notif_inapp')->default(true)->after('notif_email');
            $table->boolean('notif_push')->default(false)->after('notif_inapp');
            $table->boolean('two_factor_enabled')->default(false)->after('notif_push');
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_title','department','timezone','language','date_format','theme','notif_email','notif_inapp','notif_push','two_factor_enabled']);
        });
        Schema::dropIfExists('api_tokens');
    }
};
