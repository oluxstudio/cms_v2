<?php

namespace App\Services;

use App\Models\CreatorPayout;
use App\Models\TemplatePurchase;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * What creators are owed for template sales, and paying it out. Sales are
 * collected on the platform account; each paid sale carries the creator's
 * share. A sale becomes payable after the hold period (so most refunds land
 * first); a refund after payout is clawed back from the next payout.
 */
class CreatorPayouts
{
    public function holdDays(): int
    {
        return (int) config('templates.payout_hold_days', 7);
    }

    /** Sales ready to pay (paid, past the hold, not yet paid out). */
    private function payableSales(string $creatorId)
    {
        return TemplatePurchase::where('creator_user_id', $creatorId)
            ->where('status', 'paid')->whereNull('payout_id')
            ->where('creator_amount_cents', '>', 0)
            ->where('purchased_at', '<=', now()->subDays($this->holdDays()));
    }

    /** Refunded sales the creator was already paid for, not yet recovered. */
    private function clawbacks(string $creatorId)
    {
        return TemplatePurchase::where('creator_user_id', $creatorId)
            ->where('status', 'refunded')->whereNotNull('payout_id')->whereNull('clawback_payout_id');
    }

    /** @return array{payable:int, clawback:int, owed:int, held:int, paid_to_date:int, sales:int, gross:int} */
    public function summaryFor(string $creatorId): array
    {
        $payable = (int) $this->payableSales($creatorId)->sum('creator_amount_cents');
        $clawback = (int) $this->clawbacks($creatorId)->sum('creator_amount_cents');
        $held = (int) TemplatePurchase::where('creator_user_id', $creatorId)->where('status', 'paid')
            ->whereNull('payout_id')->where('purchased_at', '>', now()->subDays($this->holdDays()))
            ->sum('creator_amount_cents');

        return [
            'payable' => $payable,
            'clawback' => $clawback,
            'owed' => $payable - $clawback,
            'held' => $held,
            'paid_to_date' => (int) CreatorPayout::where('creator_user_id', $creatorId)->where('status', 'paid')->sum('amount_cents'),
            'sales' => TemplatePurchase::where('creator_user_id', $creatorId)->where('status', 'paid')->count(),
            'gross' => (int) TemplatePurchase::where('creator_user_id', $creatorId)->where('status', 'paid')->sum('price_cents'),
        ];
    }

    /** Every creator who has ever earned, with their summary. */
    public function creators(): Collection
    {
        $ids = TemplatePurchase::whereNotNull('creator_user_id')->distinct()->pluck('creator_user_id');

        return User::whereIn('id', $ids)->get()->map(fn (User $u) => ['user' => $u] + $this->summaryFor($u->id))
            ->sortByDesc('owed')->values();
    }

    /**
     * Pay a creator everything they're owed. method "stripe" transfers to their
     * connected Stripe account; "manual" records a payment made elsewhere.
     */
    public function payOut(User $creator, User $admin, string $method, ?string $note = null, ?object $stripe = null): CreatorPayout
    {
        $summary = $this->summaryFor($creator->id);
        if ($summary['owed'] <= 0) {
            throw new RuntimeException('Nothing is owed to this creator right now.');
        }
        if ($method === 'stripe' && (! $creator->stripe_account_id || ! $creator->stripe_charges_enabled)) {
            throw new RuntimeException('This creator has not finished connecting a Stripe account. Pay manually instead.');
        }

        $payout = DB::transaction(function () use ($creator, $admin, $method, $note, $summary) {
            $payout = CreatorPayout::create([
                'creator_user_id' => $creator->id,
                'amount_cents' => $summary['owed'],
                'currency' => (string) config('templates.currency', 'gbp'),
                'status' => 'pending',
                'method' => $method,
                'note' => $note,
                'created_by' => $admin->id,
            ]);
            $this->payableSales($creator->id)->update(['payout_id' => $payout->id]);
            $this->clawbacks($creator->id)->update(['clawback_payout_id' => $payout->id]);

            return $payout;
        });

        if ($method === 'manual') {
            $payout->update(['status' => 'paid', 'paid_at' => now()]);

            return $payout;
        }

        try {
            $client = $stripe ?? app(PlatformBilling::class)->client();
            $transfer = $client->transfers->create([
                'amount' => $payout->amount_cents,
                'currency' => $payout->currency,
                'destination' => $creator->stripe_account_id,
                'transfer_group' => 'payout_'.$payout->id,
                'description' => 'Olux template earnings',
            ], ['idempotency_key' => 'creator-payout-'.$payout->id]);
            $payout->update(['status' => 'paid', 'paid_at' => now(), 'stripe_transfer_id' => $transfer->id]);
        } catch (Throwable $e) {
            report($e);
            // Release the sales so the payout can be retried.
            TemplatePurchase::where('payout_id', $payout->id)->update(['payout_id' => null]);
            TemplatePurchase::where('clawback_payout_id', $payout->id)->update(['clawback_payout_id' => null]);
            $payout->update(['status' => 'failed', 'note' => trim(($note ? $note."\n" : '').'Stripe: '.$e->getMessage())]);
        }

        return $payout->fresh();
    }
}
