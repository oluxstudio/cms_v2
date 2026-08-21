<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Encrypted copy of the raw key — set ONLY for Site Connect tokens, which are
// public-by-design (they ship in the client's HTML). Lets the build fetch the
// token with a management key instead of the developer copying it into .env.
// Management keys stay hash-only, never retrievable.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->text('plain')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn('plain');
        });
    }
};
