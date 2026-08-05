<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Polite payment nudge — upcoming due date or overdue balance. */
class InvoiceReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public Site $site,
    ) {}

    public function envelope(): Envelope
    {
        $name = ucwords(str_replace('-', ' ', $this->site->name));
        $verb = $this->invoice->status === 'overdue' ? 'is overdue' : 'is due soon';

        return new Envelope(subject: "Reminder: invoice {$this->invoice->number} from {$name} {$verb}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.invoice-reminder');
    }
}
