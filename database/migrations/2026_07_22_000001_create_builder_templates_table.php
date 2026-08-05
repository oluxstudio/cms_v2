<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A builder template: EVERYTHING the user built, snapshotted — the
        // page's content blocks, the layout tree it sits in, and the theme.
        Schema::create('builder_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);   // the free slot everyone has
            $table->json('payload');                          // {content, layout, layout_name, theme}
            $table->timestamps();
        });

        // How many templates this account may keep. 1 = the free/default slot;
        // a billing plan raises it.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('template_limit')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_templates');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('template_limit');
        });
    }
};
