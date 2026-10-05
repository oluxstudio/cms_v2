<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Site;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Polite payment nudge — upcoming due date or overdue balance. Template `invoice_reminder`. */
class InvoiceReminder extends Mailable implements ShouldQueue
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
            'status_verb' => $this->invoice->status === 'overdue' ? 'is overdue' : 'is due soon',
        ];
    }

    public function envelope(): Envelope
    {
        $tpl = EmailTemplate::forKey($this->site, 'invoice_reminder');

        return new Envelope(...$this->site->mailSender(), subject: EmailTemplate::fill($tpl['subject'], $this->ctx()));
    }

    public function content(): Content
    {
        $tpl = EmailTemplate::forKey($this->site, 'invoice_reminder');
        $inv = $this->invoice;
        $pixel = '<img src="'.url("preview/{$this->site->name}/invoice/{$inv->public_token}/open.gif").'" width="1" height="1" alt="">';

        return new Content(view: 'emails.branded', with: [
            'site' => $this->site,
            'logo' => $this->site->brandLogo(),
            'sections' => EmailTemplate::renderSections($tpl, $this->ctx()),
            'dynamic' => ['invoice_summary' => [
                'number' => $inv->number,
                'total' => $inv->formattedTotal(),
                'due' => $inv->due_date?->format('F j, Y'),
                'items' => [],
                'tax' => null,
                'pay_url' => $inv->payUrl(),
                'portal_url' => $inv->portalUrl(),
            ]],
            'trailer' => $pixel,
        ]);
    }
}
