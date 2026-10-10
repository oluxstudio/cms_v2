<?php

namespace App\Modules\Network\Mail;

use App\Modules\Network\Models\Referral;
use App\Modules\Network\ReferralManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To the REFERRER site owner: what happened to a referral they sent.
 * $event = shared | consent_refused | accepted | declined | converted.
 */
class ReferralUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const EVENTS = ['shared', 'consent_refused', 'accepted', 'declined', 'converted'];

    public function __construct(
        public Referral $referral,
        public string $event,
    ) {}

    private function lines(): array
    {
        $r = $this->referral;
        $to = ReferralManager::businessName($r->toSite);
        $who = $r->customer_name;
        $net = '£'.number_format($r->netCents() / 100, 2);

        return match ($this->event) {
            'shared' => ["{$who} said yes", "{$who} agreed to be introduced, so their details are now with {$to}."],
            'consent_refused' => ["{$who} said no thanks", "{$who} chose not to have their details passed to {$to}. Nothing was shared."],
            'accepted' => ["{$to} accepted your referral", "{$to} has accepted your referral of {$who} and will be in touch with them."],
            'declined' => ["{$to} declined your referral", "{$to} can't take on {$who} this time.".($r->decline_reason ? " Their reason: \"{$r->decline_reason}\"" : '')],
            'converted' => ["Your referral converted — {$net} on its way", "{$who} became a paying customer of {$to}. Once the ".(int) config('network.dispute_days')."-day check has passed you'll earn {$net} (the {$to} fee minus Olux's cut)."],
            default => ['Referral update', "There's an update on your referral of {$who} to {$to}."],
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->lines()[0].' ('.$this->referral->reference.')');
    }

    public function content(): Content
    {
        [$headline, $body] = $this->lines();
        $from = $this->referral->fromSite;

        return new Content(view: 'emails.network.update', with: [
            'referral' => $this->referral,
            'event' => $this->event,
            'headline' => $headline,
            'body' => $body,
            'url' => $from ? route('site.network', $from->name).'?tab=sent' : url('/'),
        ]);
    }
}
