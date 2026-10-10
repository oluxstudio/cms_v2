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

/** To the customer, from the REFERRER: "May we pass your details to X?" — links to the consent page. */
class ReferralConsentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Referral $referral,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        $to = ReferralManager::businessName($this->referral->toSite);

        return new Envelope(...ReferralManager::sender($this->referral->fromSite),
            subject: "May we pass your details to {$to}?");
    }

    public function content(): Content
    {
        $r = $this->referral;

        return new Content(view: 'emails.network.consent', with: [
            'fromName' => ReferralManager::businessName($r->fromSite),
            'toName' => ReferralManager::businessName($r->toSite),
            'logo' => $r->fromSite?->brandLogo(),
            'recipient' => $r->customer_name,
            'consentText' => $r->consent_text,
            'url' => route('network.consent.show', $this->token),
            'days' => (int) config('network.consent_days'),
        ]);
    }
}
