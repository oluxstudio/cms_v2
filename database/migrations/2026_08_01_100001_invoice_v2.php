<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice v2 — tracking (opened/viewed), automated reminders, recurring
 * billing. Guarded per-step (MySQL DDL is non-transactional).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'opened_at'         => fn (Blueprint $t) => $t->timestamp('opened_at')->nullable()->after('sent_at'),
            'viewed_at'         => fn (Blueprint $t) => $t->timestamp('viewed_at')->nullable()->after('opened_at'),
            'reminded_at'       => fn (Blueprint $t) => $t->timestamp('reminded_at')->nullable()->after('viewed_at'),
            'reminders_sent'    => fn (Blueprint $t) => $t->unsignedTinyInteger('reminders_sent')->default(0)->after('reminded_at'),
            'recur_interval'    => fn (Blueprint $t) => $t->string('recur_interval', 12)->nullable()->after('reminders_sent'),
            'recur_next_on'     => fn (Blueprint $t) => $t->date('recur_next_on')->nullable()->after('recur_interval'),
            'parent_invoice_id' => fn (Blueprint $t) => $t->foreignId('parent_invoice_id')->nullable()->after('recur_next_on')
                                                          ->constrained('invoices')->nullOnDelete(),
        ] as $col => $add) {
            if (! Schema::hasColumn('invoices', $col)) {
                Schema::table('invoices', $add);
            }
        }

        if (! collect(Schema::getIndexes('invoices'))->pluck('name')->contains('invoices_recur_idx')) {
            Schema::table('invoices', fn (Blueprint $t) => $t->index(['site_id', 'recur_next_on'], 'invoices_recur_idx'));
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            if (Schema::hasColumn('invoices', 'parent_invoice_id')) {
                $t->dropConstrainedForeignId('parent_invoice_id');
            }
        });
        Schema::table('invoices', function (Blueprint $t) {
            foreach (['opened_at', 'viewed_at', 'reminded_at', 'reminders_sent', 'recur_interval', 'recur_next_on'] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
