<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Mail\CourierInvite;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Site;
use App\Payments\PaymentManager;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Orders — the house 3-rail layout: money & fulfilment tiles left, the order
 * cards (search · status pills · sort · layout) centre, revenue trend ·
 * status mix · needs attention · top customers · related right. The detail
 * drawer keeps the lifecycle actions (ship, deliver, return, refund, courier,
 * invoice).
 */
class OrdersPage extends Component
{
    use WithLayoutMode;

    /** Order statuses that count as money in. */
    private const PAID = ['paid', 'shipped', 'delivered', 'fulfilled'];

    /** Filter pills → the statuses each one covers. Raw statuses also work (?status=pending). */
    public const GROUPS = [
        'unfulfilled' => ['pending', 'paid', 'return_requested'],
        'paid' => ['paid', 'shipped', 'delivered', 'fulfilled'],
        'fulfilled' => ['shipped', 'delivered', 'fulfilled'],
        'refunded' => ['refunded', 'returned'],
        'cancelled' => ['cancelled'],
    ];

    public const SORTS = ['newest', 'oldest', 'amount', 'amount_asc', 'customer'];

    public Site $site;

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'order')]
    public ?string $selectedId = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** newest | oldest | amount | amount_asc | customer */
    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('orders', 'grid');
        if (! $this->validFilter($this->statusFilter)) {
            $this->statusFilter = 'all';
        }
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $this->validFilter($status) ? $status : 'all';
    }

    private function validFilter(string $f): bool
    {
        return $f === 'all' || isset(self::GROUPS[$f]) || in_array($f, Order::STATUSES, true);
    }

    /** Statuses the current filter covers (null = all). */
    private function filterStatuses(): ?array
    {
        if ($this->statusFilter === 'all') {
            return null;
        }

        return self::GROUPS[$this->statusFilter] ?? [$this->statusFilter];
    }

    public function updatedSort(): void
    {
        $this->sort = in_array($this->sort, self::SORTS, true) ? $this->sort : 'newest';
    }

    public function open(string $id): void
    {
        $this->selectedId = $id;
    }

    public function closeDetail(): void
    {
        $this->selectedId = null;
    }

    public function markShipped(string $id): void
    {
        $order = $this->site->orders()->find($id);
        if ($order && $order->isPaid()) {
            $order->transitionTo('shipped', auth()->id());
            $this->successMessage = 'Order marked as shipped.';
        }
    }

    public function markDelivered(string $id): void
    {
        $order = $this->site->orders()->find($id);
        if ($order && $order->isPaid()) {
            $order->transitionTo('delivered', auth()->id());
            $this->successMessage = 'Order delivered — all done.';
        }
    }

    /** Goods came back: restocks the items and stamps returned_at. */
    public function markReturned(string $id): void
    {
        $order = $this->site->orders()->find($id);
        if ($order && ($order->isPaid() || $order->status === 'return_requested')) {
            $order->transitionTo('returned', auth()->id());
            $this->successMessage = 'Order marked as returned — items are back in stock.';
        }
    }

    /**
     * Money back: refunds through the site's payment gateway when the order
     * carries a payment reference, then marks the order refunded. On gateway
     * failure nothing changes and the error is surfaced.
     */
    public function refundOrder(string $id): void
    {
        $order = $this->site->orders()->find($id);
        if (! $order || in_array($order->status, ['pending', 'refunded', 'cancelled'], true)) {
            return;
        }

        if ($order->stripe_payment_intent) {
            try {
                app(PaymentManager::class)->for($this->site)
                    ->refund($this->site, $order->stripe_payment_intent);
            } catch (\Throwable $e) {
                Log::error('order refund failed', ['order' => $order->id, 'msg' => $e->getMessage()]);
                $this->errorMessage = 'The refund could not be processed — nothing was changed. Please try again or refund from your Stripe dashboard.';

                return;
            }
        }

        $order->transitionTo('refunded', auth()->id());
        $this->successMessage = $order->stripe_payment_intent
            ? "Refund of {$order->formattedTotal()} sent — the money is on its way back."
            : 'Order marked as refunded.';
    }

    // ── Courier invitation ────────────────────────────────────────────────
    public string $courierEmail = '';

    /** Email a courier a tokened link to the delivery page for this order. */
    public function inviteCourier(string $id): void
    {
        $email = trim($this->courierEmail);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errorMessage = 'Enter a valid email address for the courier.';

            return;
        }
        $order = $this->site->orders()->find($id);
        if (! $order || ! in_array($order->status, ['paid', 'shipped'], true)) {
            return;
        }

        $order->courier_token ??= Str::random(40);
        $order->fill(['courier_email' => $email, 'courier_invited_at' => now()])->save();
        $order->recordEvent('courier_invited', auth()->id(), $email);

        try {
            Mail::to($email)->send(new CourierInvite($order, $this->site));
            $this->successMessage = "Delivery link sent to {$email}.";
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Could not send the invitation email — please try again.';
        }
        $this->courierEmail = '';
    }

    /** Per-row status dropdown (image-style). Paid stamps paid_at once. */
    public function setStatus(string $id, string $status): void
    {
        if (! in_array($status, Order::STATUSES, true)) {
            return;
        }
        $order = $this->site->orders()->find($id);
        if (! $order || $order->status === $status) {
            return;
        }
        $order->transitionTo($status, auth()->id()); // stamps timestamps, moves stock, records history
        $this->successMessage = "Order #{$order->id} → {$status}.";
    }

    // ── Quick "Create invoice" modal ──────────────────────────────────────
    public bool $qiOpen = false;

    public string $qiName = '';

    public string $qiEmail = '';

    public string $qiDue = '';

    /** @var array<int,array{description:string,qty:int,price:string}> */
    public array $qiItems = [['description' => '', 'qty' => 1, 'price' => '']];

    public function openQuickInvoice(): void
    {
        $this->reset(['qiName', 'qiEmail']);
        $this->qiItems = [['description' => '', 'qty' => 1, 'price' => '']];
        $this->qiDue = now()->addDays((int) ($this->site->feature('invoices')['due_days'] ?? 14))->format('Y-m-d');
        $this->qiOpen = true;
    }

    public function qiAddItem(): void
    {
        $this->qiItems[] = ['description' => '', 'qty' => 1, 'price' => ''];
    }

    public function qiRemoveItem(int $i): void
    {
        unset($this->qiItems[$i]);
        $this->qiItems = array_values($this->qiItems) ?: [['description' => '', 'qty' => 1, 'price' => '']];
    }

    /** Create a draft invoice from the modal, then jump to the Invoices page. */
    public function createInvoice()
    {
        $this->validate([
            'qiName' => 'required|string|max:120',
            'qiEmail' => 'required|email|max:160',
            'qiDue' => 'nullable|date',
            'qiItems' => 'required|array|min:1',
            'qiItems.*.description' => 'required|string|max:200',
            'qiItems.*.qty' => 'required|integer|min:1|max:10000',
            'qiItems.*.price' => 'required|numeric|min:0',
        ]);

        $invoice = new Invoice([
            'site_id' => $this->site->id,
            'number' => Invoice::nextNumber($this->site),
            'customer_name' => $this->qiName,
            'customer_email' => $this->qiEmail,
            'items' => collect($this->qiItems)->map(fn ($i) => [
                'description' => trim($i['description']),
                'qty' => (int) $i['qty'],
                'unit_cents' => (int) round(((float) $i['price']) * 100),
            ])->values()->all(),
            'tax_bp' => (int) round(((float) ($this->site->feature('invoices')['tax_percent'] ?? 0)) * 100),
            'currency' => $this->site->currency ?? 'gbp',
            'status' => 'draft',
            'due_date' => $this->qiDue ?: null,
        ]);
        $invoice->recalc();
        $invoice->save();

        $this->qiOpen = false;

        return redirect(url($this->site->name.'/invoices'));
    }

    /** Turn an order into a draft invoice (visible on the Invoices page). */
    public function invoiceOrder(string $id)
    {
        $order = $this->site->orders()->with('items')->find($id);
        if (! $order || $order->items->isEmpty()) {
            return;
        }

        $marker = "From order #{$order->id}";
        if (Invoice::where('site_id', $this->site->id)->where('notes', 'like', "%{$marker}%")->exists()) {
            $this->successMessage = 'This order already has an invoice.';

            return;
        }

        $invoice = new Invoice([
            'site_id' => $this->site->id,
            'number' => Invoice::nextNumber($this->site),
            'customer_name' => $order->customer_name ?: ($order->customer_email ?: 'Customer'),
            'customer_email' => $order->customer_email ?: 'unknown@example.com',
            'items' => $order->items->map(fn ($it) => [
                'description' => $it->name,
                'qty' => (int) $it->qty,
                'unit_cents' => (int) $it->price_cents,
            ])->values()->all(),
            'tax_bp' => 0,
            'currency' => $order->currency ?? $this->site->currency ?? 'gbp',
            'status' => 'draft',
            'due_date' => now()->addDays((int) ($this->site->feature('invoices')['due_days'] ?? 14))->toDateString(),
            'notes' => $marker,
        ]);
        $invoice->recalc();
        $invoice->save();

        return redirect(url($this->site->name.'/invoices'));
    }

    public function getOrdersProperty()
    {
        $term = trim($this->search);

        $statuses = $this->filterStatuses();
        $bare = ltrim($term, '#');

        return $this->site->orders()
            ->withCount('items')
            ->withSum('items as units', 'qty')
            ->when($statuses !== null, fn ($q) => $q->whereIn('status', $statuses))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('id', $bare)
                ->orWhere('order_number', 'like', "%{$bare}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('customer_email', 'like', "%{$term}%")
                ->orWhereHas('items', fn ($i) => $i->where('name', 'like', "%{$term}%"))))
            ->when($this->sort === 'amount', fn ($q) => $q->orderByDesc('total_cents'))
            ->when($this->sort === 'amount_asc', fn ($q) => $q->orderBy('total_cents'))
            ->when($this->sort === 'customer', fn ($q) => $q->orderByRaw("COALESCE(NULLIF(customer_name, ''), customer_email) IS NULL")->orderByRaw("COALESCE(NULLIF(customer_name, ''), customer_email)"))
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest(), fn ($q) => $q->latest())
            ->get();
    }

    public function getSelectedProperty(): ?Order
    {
        if (! $this->selectedId) {
            return null;
        }

        return $this->site->orders()->with(['items', 'events.user:id,name'])->find($this->selectedId);
    }

    public function getStatusCountsProperty(): array
    {
        $counts = $this->site->orders()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $counts['all'] = array_sum($counts);
        // Pill groups (Unfulfilled / Paid / Fulfilled / Refunded / Cancelled).
        foreach (self::GROUPS as $group => $statuses) {
            $counts['group:'.$group] = array_sum(array_intersect_key($counts, array_flip($statuses)));
        }

        return $counts;
    }

    /** % change helper: null when there is no previous value to compare. */
    private function delta(int|float $now, int|float $prev): ?int
    {
        return $prev > 0 ? (int) round(($now - $prev) / $prev * 100) : null;
    }

    /**
     * Rail numbers — month stats + deltas, daily revenue, top products,
     * refunds, repeat & top customers, needs-attention counts. Grouped
     * queries only: the cost doesn't grow with the date range or statuses.
     */
    public function getInsightsProperty(): array
    {
        $currency = $this->site->currency ?? 'gbp';
        $paid = fn () => $this->site->orders()->whereIn('status', self::PAID);

        $monthStart = now()->startOfMonth();
        $prevStart = now()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $monthStart->copy()->subSecond();

        $monthRevenue = (int) $paid()->where('paid_at', '>=', $monthStart)->sum('total_cents');
        $prevRevenue = (int) $paid()->whereBetween('paid_at', [$prevStart, $prevEnd])->sum('total_cents');

        $monthOrders = $this->site->orders()->where('created_at', '>=', $monthStart)->count();
        $prevOrders = $this->site->orders()->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $customers = $this->site->orders()->whereNotNull('customer_email')
            ->where('created_at', '>=', $monthStart)->distinct('customer_email')->count('customer_email');
        $prevCustomers = $this->site->orders()->whereNotNull('customer_email')
            ->whereBetween('created_at', [$prevStart, $prevEnd])->distinct('customer_email')->count('customer_email');

        $paidOrders = $paid()->count();
        $revenueAll = (int) $paid()->sum('total_cents');

        // Last 14 days of revenue → the trend bars (one grouped query).
        $since = now()->subDays(13)->startOfDay();
        $byDay = $paid()->where('paid_at', '>=', $since)
            ->selectRaw('DATE(paid_at) as d, SUM(total_cents) as cents')
            ->groupBy('d')->pluck('cents', 'd');
        $daily = collect(range(13, 0))->map(function ($d) use ($byDay) {
            $date = now()->subDays($d);

            return ['label' => $date->format('j M'), 'cents' => (int) ($byDay[$date->toDateString()] ?? 0)];
        })->all();

        // Sales by product (top products by revenue share).
        $cats = OrderItem::whereIn('order_id', $paid()->select('id'))
            ->selectRaw('name, sum(price_cents * qty) as revenue_cents, sum(qty) as units')
            ->groupBy('name')->orderByDesc('revenue_cents')->limit(5)->get();
        $catTotal = max(1, (int) $cats->sum('revenue_cents'));
        $categories = $cats->map(fn ($r) => [
            'name' => $r->name,
            'units' => (int) $r->units,
            'cents' => (int) $r->revenue_cents,
            'share' => round($r->revenue_cents / $catTotal * 100, 1),
            'revenue' => Money::format((int) $r->revenue_cents, $currency),
        ])->all();

        // Customers by email across every paid-ish order (refunds excluded).
        $byCustomer = $this->site->orders()->whereIn('status', [...self::PAID, 'return_requested', 'returned'])
            ->whereNotNull('customer_email')->where('customer_email', '!=', '')
            ->selectRaw('customer_email as email, MAX(customer_name) as name, COUNT(*) as n, SUM(total_cents) as cents')
            ->groupBy('customer_email')->get();
        $refunds = $this->site->orders()->where('status', 'refunded')
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total_cents), 0) as cents')->first();

        return [
            'currency' => $currency,
            'monthRevenue' => Money::format($monthRevenue, $currency),
            'revDelta' => $this->delta($monthRevenue, $prevRevenue),
            'monthOrders' => $monthOrders,
            'ordDelta' => $this->delta($monthOrders, $prevOrders),
            'customers' => $customers,
            'custDelta' => $this->delta($customers, $prevCustomers),
            'aov' => Money::format($paidOrders > 0 ? (int) round($revenueAll / $paidOrders) : 0, $currency),
            'revenueAll' => Money::format($revenueAll, $currency),
            'awaiting' => $this->site->orders()->where('status', 'pending')->count(),
            'waitingPeople' => $this->site->orders()->where('status', 'pending')
                ->whereNotNull('customer_email')->distinct('customer_email')->count('customer_email'),
            // Checkouts left unpaid for over a day — likely failed or abandoned payments.
            'stalePending' => $this->site->orders()->where('status', 'pending')->where('created_at', '<', now()->subDay())->count(),
            'refunds' => (int) ($refunds->n ?? 0),
            'refundedTotal' => Money::format((int) ($refunds->cents ?? 0), $currency),
            'customersAll' => $byCustomer->count(),
            'repeatCustomers' => $byCustomer->where('n', '>', 1)->count(),
            'topCustomers' => $byCustomer->sortByDesc('cents')->take(5)->map(fn ($c) => [
                'email' => $c->email,
                'name' => $c->name ?: $c->email,
                'orders' => (int) $c->n,
                'cents' => (int) $c->cents,
                'total' => Money::format((int) $c->cents, $currency),
            ])->values()->all(),
            'daily' => $daily,
            'categories' => $categories,
        ];
    }

    public function render()
    {
        return view('livewire.orders-page');
    }
}
