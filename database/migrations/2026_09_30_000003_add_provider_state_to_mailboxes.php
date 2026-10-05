<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provisioning checkpoint (e.g. "licence_ordered") so a retried job
        // resumes instead of buying a second mailbox licence.
        Schema::table('mailboxes', fn (Blueprint $t) => $t->string('provider_state', 32)->nullable()->after('provider_reference'));
    }

    public function down(): void
    {
        Schema::table('mailboxes', fn (Blueprint $t) => $t->dropColumn('provider_state'));
    }
};
