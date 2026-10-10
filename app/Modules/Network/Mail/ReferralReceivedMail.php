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

/** To the RECEIVING site owner: a referred customer (who consented) is now in your contacts. */
class ReferralReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Referral $referral) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New referral from '.ReferralManager::businessName($this->referral->fromSite)
            .' — '.$this->referral->customer_name);
    }

    public function content(): Content
    {
        $r = $this->referral;

        return new Content(view: 'emails.network.received', with: [
            'referral' => $r,
            'fromName' => ReferralManager::businessName($r->fromSite),
            'toName' => ReferralManager::businessName($r->toSite),
            'fee' => '£'.number_format($r->fee_cents / 100, 2),
            'days' => (int) config('network.conversion_window_days'),
            'url' => $r->toSite ? route('site.network', $r->toSite->name).'?tab=received' : url('/'),
        ]);
    }
}
