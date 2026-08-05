<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_github_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('token')->nullable();          // encrypted PAT (repo scope)
            $table->string('owner')->nullable();         // github user / org
            $table->string('repo')->nullable();          // repo name
            $table->string('branch')->default('main');
            $table->string('pages_url')->nullable();     // GitHub Pages URL once enabled
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_github_settings');
    }
};
