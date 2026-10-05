<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Site;
use App\Support\EmailTemplate;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Purchase confirmation to the buyer, linking the live order-status page. Template `order_confirmed`. */
class OrderConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public Site $site) {}

    private function business(): string
    {
        return (string) $this->site->getAttr('business_name', ucwords(str_replace('-', ' ', $this->site->name)));
    }

    private function ctx(): array
    {
        return [
            'name' => $this->order->customer_name,
            'site' => ucwords(str_replace('-', ' ', $this->site->name)),
            'business' => $this->business(),
            'total' => $this->order->formattedTotal(),
            'order_number' => $this->order->displayNumber(),
        ];
    }

    public function envelope(): Envelope
    {
        $tpl = EmailTemplate::forKey($this->site, 'order_confirmed');

        return new Envelope(...$this->site->mailSender(), subject: EmailTemplate::fill($tpl['subject'], $this->ctx()));
    }

    public function content(): Content
    {
        $tpl = EmailTemplate::forKey($this->site, 'order_confirmed');
        $o = $this->order;

        return new Content(view: 'emails.branded', with: [
            'site' => $this->site,
            'logo' => $this->site->brandLogo(),
            'sections' => EmailTemplate::renderSections($tpl, $this->ctx()),
            'dynamic' => ['order_lines' => [
                'items' => $o->items->map(fn ($it) => [
                    'qty' => $it->qty,
                    'name' => $it->name,
                    'amount' => Money::format($it->lineTotalCents(), $o->currency),
                ])->all(),
                'total' => $o->formattedTotal(),
                'vat' => $o->vatLabel() ?: null,
                'number' => $o->displayNumber(),
                'fulfilment' => $o->fulfilment,
                'shipping_address' => $o->shipping_address,
                'status_url' => $o->statusUrl(),
            ]],
        ]);
    }
}
