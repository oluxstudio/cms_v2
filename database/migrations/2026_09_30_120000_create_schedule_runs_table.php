<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per scheduled-task run (and per "Run now" from the admin).
        Schema::create('schedule_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 120)->index();
            $table->string('status', 12)->default('running'); // running | ok | failed
            $table->string('trigger', 12)->default('schedule'); // schedule | manual
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->text('output')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_runs');
    }
};
