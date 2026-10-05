<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A site's previous web addresses. When a site changes its address
 * (sites.name), the old one is kept here so old links, subdomains and
 * integrations (Stripe webhooks, Site Connect) keep reaching it, and no
 * other site can claim it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_aliases', function (Blueprint $table) {
            $table->id();
            $table->char('site_id', 26)->index();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_aliases');
    }
};
