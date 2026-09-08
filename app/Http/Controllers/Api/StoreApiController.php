<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Site;
use App\Payments\CheckoutLine;
use App\Payments\CheckoutRequest;
use App\Payments\PaymentManager;
use App\Services\TaskLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Headless store API for template sites (same pattern as the booking API):
 * list products, show one, and start a Stripe checkout for a cart of lines.
 * The webhook (Connect or per-site) marks the order paid and stock is
 * decremented by Order::markPaid().
 */
class StoreApiController extends Controller
{
    public function __construct(private PaymentManager $payments) {}

    private function site(string $siteName): Site
    {
        return Site::where('name', $siteName)->firstOrFail();
    }

    /** Store-wide reviews master switch (site attribute, default ON). */
    private function storeReviewsOn(Site $site): bool
    {
        return $site->getAttr('store_reviews_enabled') !== '0';
    }

    private function record(Product $p, ?bool $storeOn = null): array
    {
        $reviewsOn = ($storeOn ?? $this->storeReviewsOn($p->site)) && $p->reviews_enabled;

        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'name' => $p->name,
            'description' => $p->description,
            'price_cents' => (int) $p->price_cents,
            'price' => $p->formattedPrice(),
            'currency' => $p->currency,
            'image' => $p->image ? Storage::url($p->image) : null,
            'inventory' => $p->inventory,
            'in_stock' => $p->inStock(),
            'category' => $p->category,
            'tags' => array_values($p->tags ?? []),
            'reviews_enabled' => $reviewsOn,
            'rating' => $reviewsOn && $p->reviews()->approved()->avg('rating') ? round($p->reviews()->approved()->avg('rating'), 1) : null,
            'review_count' => $reviewsOn ? $p->reviews()->approved()->count() : 0,
        ];
    }

    /** Interest beacon from the storefront: view | add_to_cart. Always 204. */
    public function event(string $siteName, string $slug, Request $request)
    {
        $site = $this->site($siteName);
        $data = $request->validate([
            'event' => ['required', 'in:view,add_to_cart'],
            'session' => ['nullable', 'string', 'max:64'],
        ]);
        $product = $site->products()->where('slug', $slug)->where('is_active', true)->first();
        if ($product) {
            $product->events()->create([
                'site_id' => $site->id,
                'event' => $data['event'],
                'session_hash' => $data['session'] ?? null,
            ]);
        }

        return response()->noContent();
    }

    public function reviews(string $siteName, string $slug): JsonResponse
    {
        $site = $this->site($siteName);
        $product = $site->products()->where('slug', $slug)->firstOrFail();
        $approved = $product->reviews()->approved()->latest()->take(50)->get();

        return response()->json([
            'reviews' => $approved->map(fn ($r) => [
                'name' => $r->name, 'rating' => $r->rating, 'body' => $r->body,
                'date' => $r->created_at->toDateString(),
            ])->values(),
            'summary' => [
                'count' => $approved->count(),
                'average' => $approved->count() ? round($approved->avg('rating'), 1) : null,
            ],
        ]);
    }

    public function submitReview(string $siteName, string $slug, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $product = $site->products()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        if (! $this->storeReviewsOn($site) || ! $product->reviews_enabled) {
            return response()->json(['message' => 'Reviews are closed for this product.'], 409);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $product->reviews()->create($data + ['site_id' => $site->id, 'status' => 'pending']);

        try {
            app(TaskLogger::class)->alert($site,
                'New review awaiting approval — '.$product->name, 'review', 'info',
                '★'.$data['rating'].' from '.$data['name'],
                null, 'all', url($site->name.'/store/'.$product->id));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true, 'message' => 'Thanks — your review is awaiting approval.'], 201);
    }

    public function index(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);

        $products = $site->products()->where('is_active', true)
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('tag'), fn ($q, $t) => $q->whereJsonContains('tags', $t))
            ->orderBy('sort')->latest()->get();

        $storeOn = $this->storeReviewsOn($site);

        return response()->json([
            'products' => $products->map(fn (Product $p) => $this->record($p, $storeOn))->values(),
            // Distinct categories across the whole ACTIVE catalogue — for filter chips.
            'categories' => $site->products()->where('is_active', true)
                ->whereNotNull('category')->where('category', '!=', '')
                ->distinct()->orderBy('category')->pluck('category')->values(),
        ]);
    }

    public function show(string $siteName, string $slug): JsonResponse
    {
        $site = $this->site($siteName);
        $product = $site->products()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        return response()->json(['product' => $this->record($product, $this->storeReviewsOn($site))]);
    }

    /**
     * Post-checkout confirmation fallback: the template calls this when the
     * buyer lands back with ?paid=1. We verify the Checkout Session with the
     * gateway server-side and mark the order paid — covers local/dev setups
     * where Stripe's webhook can't reach the app. Idempotent.
     */
    public function confirmOrder(string $siteName, string $order): JsonResponse
    {
        $site = $this->site($siteName);
        $orderModel = $site->orders()->findOrFail($order);

        if ($orderModel->status === 'pending' && $orderModel->stripe_session_id) {
            try {
                $details = $this->payments->for($site)->checkoutDetails($site, $orderModel->stripe_session_id);
                if ($details) {
                    $orderModel->fill(array_filter([
                        'customer_email' => $orderModel->customer_email ?: $details->payerEmail,
                        'customer_name' => $orderModel->customer_name ?: $details->payerName,
                        'customer_phone' => $orderModel->customer_phone ?: $details->payerPhone,
                        'shipping_address' => $orderModel->shipping_address ?: $details->shippingAddress,
                    ]))->save();
                    if ($details->isPaid) {
                        $orderModel->markPaid($details->paymentRef);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('order confirm check failed', ['order' => $orderModel->id, 'msg' => $e->getMessage()]);
            }
        }

        return response()->json(['order' => $orderModel->id, 'status' => $orderModel->fresh()->displayStatus()]);
    }

    public function checkout(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.slug' => ['required', 'string'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:32'],
            'fulfilment' => ['required', 'in:delivery,collection'],
            'address_line1' => ['required_if:fulfilment,delivery', 'nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['required_if:fulfilment,delivery', 'nullable', 'string', 'max:100'],
            'postcode' => ['required_if:fulfilment,delivery', 'nullable', 'string', 'max:12'],
            'notes' => ['nullable', 'string', 'max:500'],
            'consent' => ['nullable', 'boolean'],
            'return_url' => ['nullable', 'url'],
        ]);

        $gateway = $this->payments->for($site);
        if (! $gateway->available($site)) {
            return response()->json(['message' => 'This store is not accepting payments yet — please contact us directly.'], 409);
        }

        // Resolve + stock-check every line before touching anything.
        $resolved = [];
        foreach ($data['lines'] as $line) {
            $product = $site->products()->where('slug', $line['slug'])->where('is_active', true)->first();
            if (! $product) {
                return response()->json(['message' => "Product “{$line['slug']}” is no longer available."], 422);
            }
            if (! $product->inStock((int) $line['qty'])) {
                return response()->json(['message' => "“{$product->name}” only has {$product->inventory} left in stock."], 422);
            }
            $resolved[] = [$product, (int) $line['qty']];
        }

        $total = collect($resolved)->sum(fn ($r) => $r[0]->price_cents * $r[1]);
        $currency = $resolved[0][0]->currency;

        // VAT snapshot — prices are VAT-inclusive (UK retail); 0% = not shown.
        $vatPercent = (float) ($site->feature('store')['vat_percent']
            ?? $site->feature('invoices')['tax_percent'] ?? 0);
        $vatCents = $vatPercent > 0 ? (int) round($total * $vatPercent / (100 + $vatPercent)) : 0;

        // The customer's details all come from the site's own checkout form.
        $shippingAddress = $data['fulfilment'] === 'delivery'
            ? implode("\n", array_filter([
                $data['name'],
                $data['address_line1'] ?? null,
                $data['address_line2'] ?? null,
                trim(($data['city'] ?? '').' '.strtoupper($data['postcode'] ?? '')),
            ]))
            : null;

        // Pending order first, so the webhook can find it by metadata.
        $order = $site->orders()->create([
            'customer_email' => $data['email'],
            'customer_name' => $data['name'],
            'customer_phone' => $data['phone'],
            'shipping_address' => $shippingAddress,
            'fulfilment' => $data['fulfilment'],
            'delivery_notes' => $data['notes'] ?? null,
            'marketing_consent' => (bool) ($data['consent'] ?? false),
            'status' => 'pending',
            'total_cents' => $total,
            'vat_bp' => (int) round($vatPercent * 100),
            'vat_cents' => $vatCents,
            'currency' => $currency,
        ]);
        foreach ($resolved as [$product, $qty]) {
            $order->items()->create([
                'product_id' => $product->id,
                'name' => $product->name,
                'price_cents' => $product->price_cents,
                'qty' => $qty,
            ]);
        }

        $return = rtrim((string) ($data['return_url'] ?? ''), '/');
        $successUrl = $return !== ''
            ? $return.'?order='.$order->id.'&paid=1'
            : url('preview/'.$site->name.'/store/success').'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $return !== ''
            ? $return.'?order='.$order->id.'&cancelled=1'
            : url('preview/'.$site->name.'/store');

        try {
            $session = $gateway->createCheckout($site, new CheckoutRequest(
                lines: collect($resolved)->map(fn ($r) => new CheckoutLine($r[0]->name, $r[0]->price_cents, $r[0]->currency, $r[1]))->all(),
                successUrl: $successUrl,
                cancelUrl: $cancelUrl,
                metadata: ['order_id' => $order->id, 'site_id' => $site->id, 'kind' => 'order'],
                customerEmail: $data['email'],
                // The site's own checkout form collects all customer details —
                // Stripe handles payment only.
                collectShipping: false,
            ));
        } catch (\Throwable $e) {
            Log::error('store api checkout failed', ['site' => $site->id, 'msg' => $e->getMessage()]);
            $order->update(['status' => 'cancelled']);

            return response()->json(['message' => 'Could not start checkout. Please try again later.'], 502);
        }

        $order->update(['stripe_session_id' => $session->id]);
        app(TaskLogger::class); // (alert on PAID happens in fulfilment, not here)

        return response()->json([
            'ok' => true,
            'order' => $order->id,
            'checkout_url' => $session->url,
            'total' => $order->formattedTotal(),
        ]);
    }
}
