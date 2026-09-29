<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Site;
use App\Support\EmailTemplate;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The invoice email — summary + hosted pay link. Template `invoice_sent`; PDF always attached. */
class InvoiceSent extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public Site $site,
    ) {}

    private function ctx(): array
    {
        return [
            'name' => $this->invoice->customer_name,
            'site' => ucwords(str_replace('-', ' ', $this->site->name)),
            'number' => $this->invoice->number,
            'total' => $this->invoice->formattedTotal(),
            'due' => $this->invoice->due_date?->format('F j, Y') ?? '',
        ];
    }

    /** Structured payload for the invoice_summary dynamic section. */
    public function summaryData(): array
    {
        $inv = $this->invoice;

        return [
            'number' => $inv->number,
            'total' => $inv->formattedTotal(),
            'due' => $inv->due_date?->format('F j, Y'),
            'items' => collect($inv->items)->map(fn ($item) => [
                'description' => $item['description'],
                'qty' => $item['qty'],
                'amount' => Money::format((int) $item['unit_cents'] * (int) $item['qty'], $inv->currency),
            ])->all(),
            'tax' => $inv->tax_cents > 0 ? Money::format((int) $inv->tax_cents, $inv->currency) : null,
            'pay_url' => $inv->payUrl(),
            'portal_url' => $inv->portalUrl(),
        ];
    }

    /** The letterhead PDF rides along with every invoice email. */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => Pdf::loadView('pdf.invoice', [
                    'site' => $this->site,
                    'invoice' => $this->invoice,
                ])->setPaper('a4')->output(),
                "{$this->invoice->number}.pdf",
            )->withMime('application/pdf'),
        ];
    }

    public function envelope(): Envelope
    {
        $tpl = EmailTemplate::forKey($this->site, 'invoice_sent');

        return new Envelope(subject: EmailTemplate::fill($tpl['subject'], $this->ctx()));
    }

    public function content(): Content
    {
        $tpl = EmailTemplate::forKey($this->site, 'invoice_sent');
        $pixel = '<img src="'.url("preview/{$this->site->name}/invoice/{$this->invoice->public_token}/open.gif").'" width="1" height="1" alt="">';

        return new Content(view: 'emails.branded', with: [
            'site' => $this->site,
            'logo' => $this->site->brandLogo(),
            'sections' => EmailTemplate::renderSections($tpl, $this->ctx()),
            'dynamic' => ['invoice_summary' => $this->summaryData()],
            'trailer' => $pixel, // open-tracking pixel
        ]);
    }
}
