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

/** Purchase confirmation to the buyer, with a link to the live order-status page. */
class OrderConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public Site $site) {}

    public function envelope(): Envelope
    {
        $business = $this->site->getAttr('business_name', ucwords(str_replace('-', ' ', $this->site->name)));

        return new Envelope(subject: "Order confirmed — thank you! · {$business}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.order-confirmed', with: [
            'statusUrl' => $this->order->statusUrl(),
            'business' => $this->site->getAttr('business_name', ucwords(str_replace('-', ' ', $this->site->name))),
        ]);
    }
}
