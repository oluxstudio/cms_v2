<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The invoice email — summary + hosted pay link. */
class InvoiceSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public Site $site,
    ) {}

    public function envelope(): Envelope
    {
        $name = ucwords(str_replace('-', ' ', $this->site->name));

        return new Envelope(subject: "Invoice {$this->invoice->number} from {$name} — {$this->invoice->formattedTotal()}");
    }

    /** The letterhead PDF rides along with every invoice email. */
    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
                    'site'    => $this->site,
                    'invoice' => $this->invoice,
                ])->setPaper('a4')->output(),
                "{$this->invoice->number}.pdf",
            )->withMime('application/pdf'),
        ];
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.invoice-sent');
    }
}
