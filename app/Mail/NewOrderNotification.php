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

/** New paid order notification to the site owner. */
class NewOrderNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public Site $site) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "💰 New order {$this->order->formattedTotal()} — ".ucwords(str_replace('-', ' ', $this->site->name)));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.new-order', with: [
            'adminUrl' => url($this->site->name.'/orders?order='.$this->order->id),
        ]);
    }
}
