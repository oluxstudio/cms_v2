<?php

namespace App\Modules\Network;

use App\Services\PlatformBilling;
use Stripe\StripeClient;

/**
 * The only place the Referral Network talks to Stripe (the PLATFORM account).
 * Kept tiny so tests can swap it: app()->instance(NetworkStripe::class, $fake).
 * Every method returns the Stripe object id it created.
 */
class NetworkStripe
{
    public function configured(): bool
    {
        return app(PlatformBilling::class)->configured();
    }

    protected function client(): StripeClient
    {
        return app(PlatformBilling::class)->client();
    }

    /** Pending invoice item on a customer (lands on their next subscription invoice). */
    public function createInvoiceItem(array $params, string $idempotencyKey): string
    {
        return $this->client()->invoiceItems->create($params, ['idempotency_key' => $idempotencyKey])->id;
    }

    /** Platform → connected account transfer. */
    public function createTransfer(array $params, string $idempotencyKey): string
    {
        return $this->client()->transfers->create($params, ['idempotency_key' => $idempotencyKey])->id;
    }

    /** Customer balance transaction (negative amount = credit towards their next invoices). */
    public function createBalanceTransaction(string $customerId, array $params, string $idempotencyKey): string
    {
        return $this->client()->customers->createBalanceTransaction($customerId, $params, ['idempotency_key' => $idempotencyKey])->id;
    }

    /** Every invoice-item id on an invoice (used when the webhook payload's line list is truncated). */
    public function invoiceItemIdsOn(string $invoiceId): array
    {
        $ids = [];
        foreach ($this->client()->invoices->allLines($invoiceId, ['limit' => 100])->autoPagingIterator() as $line) {
            if ($id = self::lineInvoiceItemId($line)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** The invoice item behind an invoice line (new API: parent.invoice_item_details; old API: invoice_item). */
    public static function lineInvoiceItemId(object $line): ?string
    {
        $id = $line->parent->invoice_item_details->invoice_item ?? ($line->invoice_item ?? null);
        $id = is_object($id) ? ($id->id ?? null) : $id;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** Invoice-item ids from a webhook invoice object, fetching all lines when the payload list is truncated. */
    public function invoiceItemIdsFrom(object $invoice): array
    {
        if (($invoice->lines->has_more ?? false) && filled($invoice->id ?? null)) {
            return $this->invoiceItemIdsOn((string) $invoice->id);
        }
        $ids = [];
        foreach ($invoice->lines->data ?? [] as $line) {
            if ($id = self::lineInvoiceItemId($line)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
