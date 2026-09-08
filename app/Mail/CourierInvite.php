<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Invite for a courier: link to the tokened delivery page for one order. */
class CourierInvite extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public Site $site) {}

    public function envelope(): Envelope
    {
        $business = $this->site->getAttr('business_name', ucwords(str_replace('-', ' ', $this->site->name)));

        return new Envelope(subject: "🚚 Delivery request — order {$this->order->displayNumber()} · {$business}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.courier-invite', with: [
            'courierUrl' => $this->order->courierUrl(),
            'business' => $this->site->getAttr('business_name', ucwords(str_replace('-', ' ', $this->site->name))),
        ]);
    }
}
