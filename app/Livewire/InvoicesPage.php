<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Mail\InvoiceSent;
use App\Models\Booking;
use App\Models\Donation;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Invoices — create/send invoices with a hosted pay link and track their
 * status (draft → sent → paid / overdue / cancelled). House 3-rail layout:
 * actionable stat tiles (left), a filterable grid/list of invoices (centre),
 * receivables summary, needs-attention, top customers and related links (right).
 */
class InvoicesPage extends Component
{
    use WithLayoutMode;
    use WithPagination;

    public Site $site;

    // Invoice form
    public ?string $editingId = null;

    public string $customerName = '';

    public string $customerEmail = '';

    public string $dueDate = '';

    public string $taxPercent = '0';

    public string $invNotes = '';

    public string $recurInterval = '';   // '' = one-off

    public string $invCurrency = '';     // '' = site default

    /** @var array<int,array{description:string,qty:int|string,price:string}> */
    public array $items = [['description' => '', 'qty' => 1, 'price' => '']];

    /** List filter: all | draft | sent | overdue | paid | recurring | void (+ tile-only: outstanding, month) */
    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'client', except: 'all')]
    public string $clientFilter = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** List order: newest | due | amount */
    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public const FILTERS = ['all', 'draft', 'sent', 'overdue', 'paid', 'recurring', 'void', 'outstanding', 'month'];

    public const SORTS = ['newest', 'due', 'amount'];

    // AI-ish quick generator: free-text prompt → draft invoice
    public string $genPrompt = '';

    // Create/edit form lightbox
    public bool $formOpen = false;

    public function openForm(): void
    {
        $this->resetForm();
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->formOpen = false;
    }

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('invoices', 'grid');
        Invoice::sweep($site); // lazy: overdue refresh + recurring + reminders
        $cfg = $site->feature('invoices');
        $this->dueDate = now()->addDays((int) ($cfg['due_days'] ?? 14))->format('Y-m-d');
        $this->taxPercent = (string) ($cfg['tax_percent'] ?? 0);
        if (! in_array($this->statusFilter, self::FILTERS, true)) {
            $this->statusFilter = $this->statusFilter === 'cancelled' ? 'void' : 'all';
        }
        if (! in_array($this->sort, self::SORTS, true)) {
            $this->sort = 'newest';
        }
    }

    #[Computed]
    public function invoices()
    {
        $needle = trim($this->search);
        $page = Invoice::where('site_id', $this->site->id)
            ->with('parent:id,number')
            ->tap(fn ($q) => match ($this->statusFilter) {
                'draft', 'sent', 'overdue', 'paid' => $q->where('status', $this->statusFilter),
                'void' => $q->where('status', 'cancelled'),
                'recurring' => $q->whereIn('recur_interval', Invoice::RECUR_INTERVALS),
                'outstanding' => $q->collectible(),
                'month' => $q->where('created_at', '>=', now()->startOfMonth()),
                default => $q,
            })
            ->when($this->clientFilter !== 'all', fn ($q) => $q->where('customer_email', $this->clientFilter))
            ->when($needle !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('number', 'like', '%'.$needle.'%')
                ->orWhere('customer_name', 'like', '%'.$needle.'%')
                ->orWhere('customer_email', 'like', '%'.$needle.'%')))
            ->tap(fn ($q) => match ($this->sort) {
                'due' => $q->orderByRaw('due_date IS NULL')->orderBy('due_date'),
                'amount' => $q->orderByDesc('total_cents'),
                default => $q->orderByDesc('created_at'),
            })
            ->orderByDesc('id')
            ->paginate(12);

        // payUrl() reads the site name — hand every row the loaded site (no N+1).
        $page->getCollection()->each->setRelation('site', $this->site);

        return $page;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedClientFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        if (! in_array($this->sort, self::SORTS, true)) {
            $this->sort = 'newest';
        }
        $this->resetPage();
    }

    public function filterClient(string $email): void
    {
        $this->clientFilter = $this->clientFilter === $email ? 'all' : $email;
        $this->resetPage();
        unset($this->invoices);
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'clientFilter']);
        $this->resetPage();
        unset($this->invoices);
    }

    /**
     * Everything the rails need, from ONE lightweight query over the site's
     * invoices: tiles, filter counts, aged receivables, needs-attention lists,
     * top customers and the plan's monthly allowance.
     */
    #[Computed]
    public function overview(): array
    {
        $all = Invoice::where('site_id', $this->site->id)->get([
            'id', 'number', 'customer_name', 'customer_email', 'status', 'total_cents', 'currency',
            'due_date', 'created_at', 'sent_at', 'paid_at', 'recur_interval', 'stripe_session_id',
            'reminders_sent', 'parent_invoice_id',
        ]);
        $currency = $this->site->currency ?: ($all->first()->currency ?? 'gbp');
        $fmt = fn (int $cents) => Money::format($cents, $currency);
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $open = $all->whereIn('status', ['sent', 'overdue']);
        $overdue = $all->where('status', 'overdue');
        $paid = $all->where('status', 'paid');
        $drafts = $all->where('status', 'draft');
        $paidMonth = $paid->filter(fn ($i) => $i->paid_at && $i->paid_at->gte($monthStart));

        $daysToPay = $paid->filter(fn ($i) => $i->paid_at)
            ->map(fn ($i) => abs(($i->sent_at ?? $i->created_at)->diffInDays($i->paid_at)));
        $avgDays = $daysToPay->isNotEmpty() ? round($daysToPay->avg(), 1) : null;

        // Aged receivables: how late each open invoice is (by due date).
        $daysLate = fn ($i) => $i->due_date && $i->due_date->lt($today) ? (int) $i->due_date->diffInDays($today) : 0;
        $aging = ['current' => 0, '1-30' => 0, '31-60' => 0, '60+' => 0];
        foreach ($open as $i) {
            $d = $daysLate($i);
            $aging[$d === 0 ? 'current' : ($d <= 30 ? '1-30' : ($d <= 60 ? '31-60' : '60+'))] += (int) $i->total_cents;
        }

        $outstanding = (int) $open->sum('total_cents');
        $collected = (int) $paid->sum('total_cents');

        $topCustomers = $all->whereNotIn('status', ['draft', 'cancelled'])
            ->groupBy(fn ($i) => strtolower($i->customer_email))
            ->map(fn ($g) => [
                'name' => $g->first()->customer_name ?: $g->first()->customer_email,
                'email' => $g->first()->customer_email,
                'cents' => (int) $g->sum('total_cents'),
                'count' => $g->count(),
                'open' => (int) $g->whereIn('status', ['sent', 'overdue'])->sum('total_cents'),
            ])
            ->sortByDesc('cents')->take(5)->values();

        $sub = $this->site->user?->currentSubscription();
        $cap = $sub?->limit('invoices_month');

        return [
            'currency' => $currency,
            'fmt' => $fmt,
            'total' => $all->count(),
            'counts' => [
                'all' => $all->count(),
                'draft' => $drafts->count(),
                'sent' => $all->where('status', 'sent')->count(),
                'overdue' => $overdue->count(),
                'paid' => $paid->count(),
                'recurring' => $all->filter(fn ($i) => in_array($i->recur_interval, Invoice::RECUR_INTERVALS, true))->count(),
                'void' => $all->where('status', 'cancelled')->count(),
                'outstanding' => $open->count(),
                'month' => $all->filter(fn ($i) => $i->created_at && $i->created_at->gte($monthStart))->count(),
            ],
            'outstanding' => $outstanding,
            'overdueCents' => (int) $overdue->sum('total_cents'),
            'collected' => $collected,
            'paidMonth' => (int) $paidMonth->sum('total_cents'),
            'paidMonthN' => $paidMonth->count(),
            'avgDays' => $avgDays,
            'paidN' => $daysToPay->count(),
            'oldestDraftDays' => $drafts->isNotEmpty() ? (int) $drafts->min('created_at')->diffInDays(now()) : null,
            'aging' => $aging,
            'chase' => $overdue->sortByDesc($daysLate)->take(4)->map(fn ($i) => $i->setAttribute('days_late', $daysLate($i)))->values(),
            'unsent' => $drafts->sortBy('created_at')->take(3)->values(),
            'failed' => $open->filter(fn ($i) => filled($i->stripe_session_id))->take(3)->values(),
            'topCustomers' => $topCustomers,
            'plan' => [
                'used' => $sub ? $sub->invoicesThisMonth() : $all->filter(fn ($i) => ! $i->parent_invoice_id && $i->created_at?->gte($monthStart))->count(),
                'cap' => $cap === null ? null : (int) $cap,
                'recurring' => $sub ? $sub->allowsRecurringInvoices() : true,
            ],
        ];
    }

    /** Revenue share by source (paid, all time) for the donut. */
    #[Computed]
    public function sourceShares(): array
    {
        $sid = $this->site->id;
        $rows = [
            ['Invoices',  (int) Invoice::where('site_id', $sid)->where('status', 'paid')->sum('total_cents')],
            ['Bookings',  (int) Booking::where('site_id', $sid)->where('status', 'confirmed')->sum('paid_cents')],
            ['Orders',    (int) Order::where('site_id', $sid)->whereIn('status', ['paid', 'fulfilled'])->sum('total_cents')],
            ['Donations', (int) Donation::where('site_id', $sid)->where('status', 'paid')->sum('amount_cents')],
        ];
        $total = max(1, array_sum(array_column($rows, 1)));

        return collect($rows)->filter(fn ($r) => $r[1] > 0)->map(fn ($r) => [
            'name' => $r[0],
            'cents' => $r[1],
            'share' => round($r[1] / $total * 100, 1),
            'money' => Money::format($r[1], $this->site->currency),
        ])->values()->all();
    }

    /** Distinct clients for the table filter. */
    #[Computed]
    public function clients()
    {
        return Invoice::where('site_id', $this->site->id)
            ->orderBy('customer_name')
            ->get(['customer_name', 'customer_email'])
            ->unique('customer_email')
            ->values();
    }

    /** Recently nudged / soon-due invoices for the Email Reminders panel. */
    #[Computed]
    public function reminderFeed()
    {
        return Invoice::where('site_id', $this->site->id)->collectible()
            ->where(fn ($q) => $q->where('reminders_sent', '>', 0)
                ->orWhere(fn ($w) => $w->whereNotNull('due_date')
                    ->whereDate('due_date', '<=', now()->addDays(7)->toDateString())))
            ->orderByRaw('COALESCE(reminded_at, due_date) DESC')
            ->limit(6)
            ->get();
    }

    /** Hero numbers: collected this month + % change vs last month. */
    #[Computed]
    public function balance(): array
    {
        $months = $this->monthly;
        $this_ = end($months);
        $prev = $months[count($months) - 2] ?? ['cents' => 0];
        $delta = $prev['cents'] > 0
            ? (int) round((($this_['cents'] - $prev['cents']) / $prev['cents']) * 100)
            : null;

        return ['cents' => $this_['cents'], 'delta' => $delta];
    }

    /**
     * "Generate by prompt": parse free text into a draft invoice.
     * Understands an email, an optional name ("for Jane Doe"), and items as
     * "description 250" / "£250 description" segments (commas/newlines/"and").
     */
    public function generateFromPrompt(): void
    {
        $text = trim($this->genPrompt);
        if ($text === '') {
            return;
        }

        preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/', $text, $em);
        $email = $em[0] ?? '';

        // Name: "for NAME" (stop at email/number/keyword) else the email user part.
        preg_match('/(?:for|to|bill)\s+([A-Z][\w\'-]*(?:\s+[A-Z][\w\'-]*){0,3})/u', $text, $nm);
        $name = trim($nm[1] ?? '') ?: Str::headline(explode('@', $email)[0] ?? '');

        // Items: segments containing an amount.
        $clean = str_ireplace([$email, $nm[0] ?? ''], '', $text);
        $items = collect(preg_split('/[,\n;]| and /i', $clean))
            ->map(function ($seg) {
                if (! preg_match('/(?:[£$€]\s*)?(\d+(?:\.\d{1,2})?)(?!\s*(?:%|days?|hours?))/', $seg, $m)) {
                    return null;
                }
                $desc = trim(preg_replace('/(?:[£$€]\s*)?'.preg_quote($m[1], '/').'/', '', $seg), " \t.-–—:x×@");
                $desc = trim(preg_replace('/\b(invoice|create|generate|charge|due|in|days?)\b/i', '', $desc), ' .,');

                return [
                    'description' => Str::ucfirst(trim($desc)) ?: 'Services',
                    'qty' => 1,
                    'price' => number_format((float) $m[1], 2, '.', ''),
                ];
            })
            ->filter()
            ->values();

        if ($email === '' || $items->isEmpty()) {
            $this->dispatch('toast', level: 'error', title: 'Could not parse',
                message: 'Include a customer email and at least one amount, e.g. "Logo design 250 for Jane jane@mail.com".');

            return;
        }

        // Prefill the form as a draft for review — the owner stays in control.
        $this->resetForm();
        $this->customerName = $name ?: 'Customer';
        $this->customerEmail = $email;
        $this->items = $items->all();
        if (preg_match('/due in (\d+) days?/i', $text, $d)) {
            $this->dueDate = now()->addDays((int) $d[1])->format('Y-m-d');
        }
        $this->saveInvoice();
        $this->genPrompt = '';
    }

    /**
     * Revenue collected per month, last 6 months, ALL sources (invoices,
     * paid bookings, paid orders, paid donations) — powers the bar chart.
     *
     * @return array<int,array{key:string,label:string,cents:int}>
     */
    #[Computed]
    public function monthly(): array
    {
        $from = now()->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->map(fn ($i) => $from->copy()->addMonths($i));

        $paidAt = fn ($q) => $q->where('site_id', $this->site->id)->where('created_at', '>=', $from);
        $sources = [
            Invoice::query()->where('status', 'paid')->whereNotNull('paid_at')
                ->where('site_id', $this->site->id)->where('paid_at', '>=', $from)
                ->get(['paid_at as at', 'total_cents as cents']),
            $paidAt(Booking::query())->where('status', 'confirmed')->where('total_cents', '>', 0)
                ->get(['created_at as at', 'total_cents as cents']),
            $paidAt(Order::query())->where('status', 'paid')->get(['created_at as at', 'total_cents as cents']),
            $paidAt(Donation::query())->where('status', 'paid')->get(['created_at as at', 'amount_cents as cents']),
        ];
        $byMonth = collect($sources)->flatten(1)->groupBy(fn ($r) => Carbon::parse($r->at)->format('Y-m'));

        return $months->map(fn ($m) => [
            'key' => $m->format('Y-m'),
            'label' => $m->format('M'),
            'cents' => (int) ($byMonth->get($m->format('Y-m'))?->sum('cents') ?? 0),
        ])->all();
    }

    /** Unified "you got paid" feed — latest 25 across every source. */
    #[Computed]
    public function payments()
    {
        $rows = collect();
        Invoice::where('site_id', $this->site->id)->where('status', 'paid')->latest('paid_at')->limit(25)->get()
            ->each(fn ($i) => $rows->push(['at' => $i->paid_at ?? $i->updated_at, 'source' => 'Invoice', 'who' => $i->customer_name, 'what' => $i->number, 'cents' => $i->total_cents, 'currency' => $i->currency]));
        Booking::where('site_id', $this->site->id)->where('status', 'confirmed')->where('total_cents', '>', 0)->latest()->limit(25)->get()
            ->each(fn ($b) => $rows->push(['at' => $b->updated_at, 'source' => 'Booking', 'who' => $b->customer_name, 'what' => $b->reference, 'cents' => $b->total_cents, 'currency' => $b->currency]));
        Order::where('site_id', $this->site->id)->where('status', 'paid')->latest()->limit(25)->get()
            ->each(fn ($o) => $rows->push(['at' => $o->updated_at, 'source' => 'Order', 'who' => $o->customer_name ?? $o->customer_email, 'what' => '#'.$o->id, 'cents' => $o->total_cents, 'currency' => $o->currency ?? $this->site->currency]));
        Donation::where('site_id', $this->site->id)->where('status', 'paid')->latest()->limit(25)->get()
            ->each(fn ($d) => $rows->push(['at' => $d->updated_at, 'source' => 'Donation', 'who' => $d->donor_name ?: ($d->donor_email ?: 'Anonymous'), 'what' => '—', 'cents' => $d->amount_cents, 'currency' => $d->currency ?? $this->site->currency]));

        return $rows->sortByDesc('at')->take(25)->values();
    }

    // ── Invoice CRUD ──────────────────────────────────────────────────────

    public function addItem(): void
    {
        $this->items[] = ['description' => '', 'qty' => 1, 'price' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items) ?: [['description' => '', 'qty' => 1, 'price' => '']];
    }

    public function saveInvoice(): void
    {
        $this->validate([
            'customerName' => 'required|string|max:120',
            'customerEmail' => 'required|email|max:160',
            'dueDate' => 'nullable|date',
            'taxPercent' => 'required|numeric|min:0|max:100',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:200',
            'items.*.qty' => 'required|integer|min:1|max:10000',
            'items.*.price' => 'required|numeric|min:0',
            'recurInterval' => 'nullable|in:,weekly,monthly,quarterly,yearly',
        ]);

        // Plan limits: invoices per month (new ones only) and recurring invoices
        // (an invoice that already repeats may keep repeating).
        $sub = $this->site->user?->currentSubscription();
        $existing = $this->editingId ? Invoice::where('site_id', $this->site->id)->find($this->editingId) : null;
        if ($sub && ! $existing && ! $sub->canCreateInvoice()) {
            $cap = (int) $sub->limit('invoices_month');
            $this->dispatch('upgrade-required', cta: 'See plans', reason: $cap === 0
                ? 'Invoicing comes with Starter and above.'
                : "Your plan includes {$cap} invoices a month and they're all used. Upgrade to Growth for unlimited invoices.");

            return;
        }
        if ($sub && $this->recurInterval && ! $sub->allowsRecurringInvoices() && ! $existing?->recur_interval) {
            $this->dispatch('upgrade-required', reason: 'Recurring invoices come with Pro and above.', cta: 'See plans');

            return;
        }

        $cfg = $this->site->feature('invoices');
        $attrs = [
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'items' => collect($this->items)->map(fn ($i) => [
                'description' => trim($i['description']),
                'qty' => (int) $i['qty'],
                'unit_cents' => (int) round(((float) $i['price']) * 100),
            ])->values()->all(),
            'tax_bp' => (int) round(((float) $this->taxPercent) * 100),
            'due_date' => $this->dueDate ?: null,
            'notes' => trim($this->invNotes) ?: null,
            'currency' => array_key_exists(strtolower($this->invCurrency), config('currencies'))
                ? strtolower($this->invCurrency) : ($this->site->currency ?? 'gbp'),
            'recur_interval' => $this->recurInterval ?: null,
        ];

        if ($this->editingId) {
            $invoice = Invoice::where('site_id', $this->site->id)->findOrFail($this->editingId);
            $invoice->fill($attrs);
        } else {
            $invoice = new Invoice($attrs + [
                'site_id' => $this->site->id,
                'number' => Invoice::nextNumber($this->site),
                'status' => 'draft',
            ]);
        }
        $isNewDraft = ! $invoice->exists;
        $invoice->recalc();
        // Recurrence pointer: first copy one interval after creation; cleared
        // when recurrence is switched off. Existing pointers are kept.
        if (! $invoice->recur_interval) {
            $invoice->recur_next_on = null;
        } elseif (! $invoice->recur_next_on) {
            $invoice->recur_next_on = match ($invoice->recur_interval) {
                'weekly' => now()->addWeek()->toDateString(),
                'quarterly' => now()->addMonths(3)->toDateString(),
                'yearly' => now()->addYear()->toDateString(),
                default => now()->addMonth()->toDateString(),
            };
        }
        $invoice->save();

        if ($isNewDraft) {
            try {
                ActivityLogger::invoiceEvent($invoice, 'created');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->resetForm();
        $this->formOpen = false;
        unset($this->viewedInvoice, $this->invoices, $this->overview);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: "Invoice {$invoice->number} saved.");
    }

    public function editInvoice(string $id): void
    {
        $i = Invoice::where('site_id', $this->site->id)->findOrFail($id);
        $this->editingId = $i->id;
        $this->customerName = $i->customer_name;
        $this->customerEmail = $i->customer_email;
        $this->dueDate = $i->due_date?->format('Y-m-d') ?? '';
        $this->taxPercent = (string) ($i->tax_bp / 100);
        $this->invNotes = (string) $i->notes;
        $this->recurInterval = (string) ($i->recur_interval ?? '');
        $this->invCurrency = (string) $i->currency;
        $this->viewingId = null; // edit from the detail drawer → swap to the form
        $this->formOpen = true;
        $this->items = collect($i->items)->map(fn ($it) => [
            'description' => $it['description'],
            'qty' => $it['qty'],
            'price' => number_format($it['unit_cents'] / 100, 2, '.', ''),
        ])->values()->all();
    }

    /** Email the invoice (pay link included) and mark it sent. */
    public function sendInvoice(string $id): void
    {
        $invoice = Invoice::where('site_id', $this->site->id)->findOrFail($id);
        if (in_array($invoice->status, ['paid', 'cancelled'], true)) {
            return;
        }

        try {
            Mail::to($invoice->customer_email)->send(new InvoiceSent($invoice, $this->site));
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', level: 'error', title: 'Not sent', message: 'The email could not be sent — check your mail settings.');

            return;
        }

        $invoice->update(['status' => $invoice->status === 'overdue' ? 'overdue' : 'sent', 'sent_at' => now()]);
        try {
            ActivityLogger::invoiceEvent($invoice, 'sent');
        } catch (\Throwable $e) {
            report($e);
        }
        unset($this->viewedInvoice, $this->invoices, $this->overview);
        $this->dispatch('toast', level: 'success', title: 'Invoice sent', message: "{$invoice->number} emailed to {$invoice->customer_email}.");
    }

    public function markPaid(string $id): void
    {
        $invoice = Invoice::where('site_id', $this->site->id)->findOrFail($id);
        $invoice->markPaid();
        unset($this->viewedInvoice, $this->invoices, $this->overview, $this->monthly, $this->payments);
        $this->dispatch('toast', level: 'success', title: 'Paid', message: "{$invoice->number} marked as paid.");
    }

    public function cancelInvoice(string $id): void
    {
        Invoice::where('site_id', $this->site->id)->whereKey($id)->update(['status' => 'cancelled']);
        unset($this->viewedInvoice, $this->invoices, $this->overview);
    }

    public function deleteInvoice(string $id): void
    {
        Invoice::where('site_id', $this->site->id)->whereKey($id)->delete();
        if ($this->editingId === $id) {
            $this->resetForm();
        }
        unset($this->viewedInvoice, $this->invoices, $this->overview, $this->monthly, $this->payments);
    }

    // ── Invoice detail (lightbox) ─────────────────────────────────────────

    public ?string $viewingId = null;

    public function viewInvoice(string $id): void
    {
        $this->viewingId = Invoice::where('site_id', $this->site->id)->whereKey($id)->exists() ? $id : null;
    }

    public function closeInvoice(): void
    {
        $this->viewingId = null;
    }

    #[Computed]
    public function viewedInvoice()
    {
        return $this->viewingId
            ? Invoice::where('site_id', $this->site->id)->find($this->viewingId)
            : null;
    }

    public function setFilter(string $status): void
    {
        $status = $status === 'cancelled' ? 'void' : $status;
        // Clicking the active tile/pill again clears it.
        $this->statusFilter = in_array($status, self::FILTERS, true) && $status !== $this->statusFilter ? $status : 'all';
        $this->resetPage();
        unset($this->viewedInvoice, $this->invoices);
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'customerName', 'customerEmail', 'invNotes', 'recurInterval']);
        $this->invCurrency = $this->site->currency ?? 'gbp';
        $cfg = $this->site->feature('invoices');
        $this->dueDate = now()->addDays((int) ($cfg['due_days'] ?? 14))->format('Y-m-d');
        $this->taxPercent = (string) ($cfg['tax_percent'] ?? 0);
        $this->items = [['description' => '', 'qty' => 1, 'price' => '']];
    }

    public function render()
    {
        return view('livewire.invoices-page');
    }
}
