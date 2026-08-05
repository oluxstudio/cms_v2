<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Site;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Store dashboard — greeting, month stats w/ deltas, revenue bars,
 * sales-by-category donut, and the order list (search/sort/filter, per-row
 * status control, order → invoice).
 */
class OrdersPage extends Component
{
    public Site $site;

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'order')]
    public ?int $selectedId = null;

    public string $search = '';
    public string $sort = 'newest';   // newest | oldest | amount

    public string $successMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
    }

    public function open(int $id): void
    {
        $this->selectedId = $id;
    }

    public function closeDetail(): void
    {
        $this->selectedId = null;
    }

    public function markFulfilled(int $id): void
    {
        $order = $this->site->orders()->find($id);
        if ($order && $order->status === 'paid') {
            $order->update(['status' => 'fulfilled']);
            $this->successMessage = 'Order marked as fulfilled.';
        }
    }

    /** Per-row status dropdown (image-style). Paid stamps paid_at once. */
    public function setStatus(int $id, string $status): void
    {
        if (! in_array($status, Order::STATUSES, true)) {
            return;
        }
        $order = $this->site->orders()->find($id);
        if (! $order || $order->status === $status) {
            return;
        }
        $order->update([
            'status'  => $status,
            'paid_at' => in_array($status, ['paid', 'fulfilled'], true)
                ? ($order->paid_at ?? now())
                : $order->paid_at,
        ]);
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
            'qiName'                => 'required|string|max:120',
            'qiEmail'               => 'required|email|max:160',
            'qiDue'                 => 'nullable|date',
            'qiItems'               => 'required|array|min:1',
            'qiItems.*.description' => 'required|string|max:200',
            'qiItems.*.qty'         => 'required|integer|min:1|max:10000',
            'qiItems.*.price'       => 'required|numeric|min:0',
        ]);

        $invoice = new Invoice([
            'site_id'        => $this->site->id,
            'number'         => Invoice::nextNumber($this->site),
            'customer_name'  => $this->qiName,
            'customer_email' => $this->qiEmail,
            'items'          => collect($this->qiItems)->map(fn ($i) => [
                'description' => trim($i['description']),
                'qty'         => (int) $i['qty'],
                'unit_cents'  => (int) round(((float) $i['price']) * 100),
            ])->values()->all(),
            'tax_bp'         => (int) round(((float) ($this->site->feature('invoices')['tax_percent'] ?? 0)) * 100),
            'currency'       => $this->site->currency ?? 'gbp',
            'status'         => 'draft',
            'due_date'       => $this->qiDue ?: null,
        ]);
        $invoice->recalc();
        $invoice->save();

        $this->qiOpen = false;

        return redirect(url($this->site->name.'/invoices'));
    }

    /** Turn an order into a draft invoice (visible on the Invoices page). */
    public function invoiceOrder(int $id)
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
            'site_id'        => $this->site->id,
            'number'         => Invoice::nextNumber($this->site),
            'customer_name'  => $order->customer_name ?: ($order->customer_email ?: 'Customer'),
            'customer_email' => $order->customer_email ?: 'unknown@example.com',
            'items'          => $order->items->map(fn ($it) => [
                'description' => $it->name,
                'qty'         => (int) $it->qty,
                'unit_cents'  => (int) $it->price_cents,
            ])->values()->all(),
            'tax_bp'         => 0,
            'currency'       => $order->currency ?? $this->site->currency ?? 'gbp',
            'status'         => 'draft',
            'due_date'       => now()->addDays((int) ($this->site->feature('invoices')['due_days'] ?? 14))->toDateString(),
            'notes'          => $marker,
        ]);
        $invoice->recalc();
        $invoice->save();

        return redirect(url($this->site->name.'/invoices'));
    }

    public function getOrdersProperty()
    {
        $term = trim($this->search);

        return $this->site->orders()
            ->withCount('items')
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('id', (int) ltrim($term, '#'))
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('customer_email', 'like', "%{$term}%")
                ->orWhereHas('items', fn ($i) => $i->where('name', 'like', "%{$term}%"))))
            ->when($this->sort === 'amount', fn ($q) => $q->orderByDesc('total_cents'),
                fn ($q) => $this->sort === 'oldest' ? $q->oldest() : $q->latest())
            ->get();
    }

    public function getSelectedProperty(): ?Order
    {
        if (! $this->selectedId) {
            return null;
        }

        return $this->site->orders()->with('items')->find($this->selectedId);
    }

    public function getStatusCountsProperty(): array
    {
        $counts = $this->site->orders()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $counts['all'] = array_sum($counts);

        return $counts;
    }

    /** % change helper: null when there is no previous value to compare. */
    private function delta(int|float $now, int|float $prev): ?int
    {
        return $prev > 0 ? (int) round(($now - $prev) / $prev * 100) : null;
    }

    /** Dashboard numbers — month stats + deltas, daily bars, category donut. */
    public function getInsightsProperty(): array
    {
        $currency = $this->site->currency ?? 'gbp';
        $paid = fn () => $this->site->orders()->whereIn('status', ['paid', 'fulfilled']);

        $monthStart = now()->startOfMonth();
        $prevStart  = now()->subMonthNoOverflow()->startOfMonth();
        $prevEnd    = $monthStart->copy()->subSecond();

        $monthRevenue = (int) $paid()->where('paid_at', '>=', $monthStart)->sum('total_cents');
        $prevRevenue  = (int) $paid()->whereBetween('paid_at', [$prevStart, $prevEnd])->sum('total_cents');

        $monthOrders = $this->site->orders()->where('created_at', '>=', $monthStart)->count();
        $prevOrders  = $this->site->orders()->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $customers     = $this->site->orders()->whereNotNull('customer_email')
            ->where('created_at', '>=', $monthStart)->distinct('customer_email')->count('customer_email');
        $prevCustomers = $this->site->orders()->whereNotNull('customer_email')
            ->whereBetween('created_at', [$prevStart, $prevEnd])->distinct('customer_email')->count('customer_email');

        $paidOrders = $paid()->count();
        $revenueAll = (int) $paid()->sum('total_cents');

        // Last 8 days of revenue → the big bar chart.
        $daily = collect(range(7, 0))->map(function ($d) {
            $date = now()->subDays($d);

            return [
                'label' => $date->format('j M'),
                'cents' => (int) $this->site->orders()
                    ->whereIn('status', ['paid', 'fulfilled'])
                    ->whereDate('paid_at', $date->toDateString())
                    ->sum('total_cents'),
            ];
        })->all();

        // Sales by category (top products by revenue share).
        $paidIds = $paid()->pluck('id');
        $cats = OrderItem::whereIn('order_id', $paidIds)
            ->selectRaw('name, sum(price_cents * qty) as revenue_cents, sum(qty) as units')
            ->groupBy('name')->orderByDesc('revenue_cents')->limit(5)->get();
        $catTotal = max(1, (int) $cats->sum('revenue_cents'));
        $categories = $cats->map(fn ($r) => [
            'name'    => $r->name,
            'units'   => (int) $r->units,
            'cents'   => (int) $r->revenue_cents,
            'share'   => round($r->revenue_cents / $catTotal * 100, 1),
            'revenue' => \App\Support\Money::format((int) $r->revenue_cents, $currency),
        ])->all();

        // Weekly per-status movement for the summary tiles.
        $weekly = [];
        foreach (Order::STATUSES as $st) {
            $thisWeek = $this->site->orders()->where('status', $st)
                ->where('updated_at', '>=', now()->startOfWeek())->count();
            $lastWeek = $this->site->orders()->where('status', $st)
                ->whereBetween('updated_at', [now()->subWeek()->startOfWeek(), now()->startOfWeek()->subSecond()])->count();
            $weekly[$st] = ['now' => $thisWeek, 'delta' => $this->delta($thisWeek, $lastWeek)];
        }

        return [
            'currency'      => $currency,
            'monthRevenue'  => \App\Support\Money::format($monthRevenue, $currency),
            'revDelta'      => $this->delta($monthRevenue, $prevRevenue),
            'monthOrders'   => $monthOrders,
            'ordDelta'      => $this->delta($monthOrders, $prevOrders),
            'customers'     => $customers,
            'custDelta'     => $this->delta($customers, $prevCustomers),
            'aov'           => \App\Support\Money::format($paidOrders > 0 ? (int) round($revenueAll / $paidOrders) : 0, $currency),
            'awaiting'      => $this->site->orders()->where('status', 'pending')->count(),
            'waitingPeople' => $this->site->orders()->where('status', 'pending')
                ->whereNotNull('customer_email')->distinct('customer_email')->count('customer_email'),
            'daily'         => $daily,
            'categories'    => $categories,
            'weekly'        => $weekly,
        ];
    }

    public function render()
    {
        return view('livewire.orders-page');
    }
}
