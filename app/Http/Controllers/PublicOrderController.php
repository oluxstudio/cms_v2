<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Site;
use Illuminate\Http\Request;

/**
 * Public tokened order pages (no auth — the token is the credential, same
 * pattern as PublicInvoiceController): the buyer's status/tracking page and
 * the invited courier's delivery page.
 */
class PublicOrderController extends Controller
{
    /** @return array{0: Site, 1: Order} */
    private function resolve(string $siteName, string $token, string $column): array
    {
        abort_if($token === '', 404);
        $site = Site::where('name', $siteName)->firstOrFail();
        $order = Order::where('site_id', $site->id)->where($column, $token)->with('items')->firstOrFail();

        return [$site, $order];
    }

    /** Buyer-facing status page — linked from the confirmation email. */
    public function status(string $siteName, string $token)
    {
        [$site, $order] = $this->resolve($siteName, $token, 'public_token');

        return view('public.store.order-status', compact('site', 'order'));
    }

    /** Courier's delivery page: address + mark picked-up / delivered. */
    public function courier(string $siteName, string $token)
    {
        [$site, $order] = $this->resolve($siteName, $token, 'courier_token');

        return view('public.store.order-courier', compact('site', 'order'));
    }

    public function courierStatus(string $siteName, string $token, Request $request)
    {
        [$site, $order] = $this->resolve($siteName, $token, 'courier_token');
        $status = $request->validate(['status' => ['required', 'in:shipped,delivered']])['status'];

        $allowed = match ($status) {
            'shipped' => $order->status === 'paid',
            'delivered' => in_array($order->status, ['paid', 'shipped'], true),
        };
        if ($allowed) {
            $order->transitionTo($status, null, 'by courier '.($order->courier_email ?: 'link'));
            $message = $status === 'delivered'
                ? 'Delivery confirmed — thank you!'
                : 'Marked as picked up. Safe travels!';
        } else {
            $message = 'This order is already '.$order->displayStatus().'.';
        }

        return redirect()->route('public.order.courier', [$siteName, $token])->with('courier-ok', $message);
    }
}
