<x-layouts.selected :siteName="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $invoice->number }} — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>

    @php $accent = $site->theme['accent'] ?? '#6366f1'; @endphp
    <script>
        // Returning from Stripe via the Back button restores this page from the
        // bfcache frozen in its mid-submit loading state (blur + spinner).
        // A persisted pageshow means "restored, not loaded" — reload for a clean state.
        window.addEventListener('pageshow', (e) => { if (e.persisted) window.location.reload(); });
    </script>
    <div class="h-full overflow-y-auto p-5 sm:p-6">
        <div class="max-w-md mx-auto">

            <a href="{{ url($site->name.'/invoices') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 mb-4">← All invoices</a>

            {{-- cream mat + dark card (booking-detail treatment) --}}
            <div class="relative"
                 style="border:.75rem solid rgba(180,160,130,.18); border-radius:28px; background:rgba(180,160,130,.18); box-shadow:0 18px 50px rgba(0,0,0,.25)">
                <x-invoice-card :invoice="$invoice" :accent="$accent" />
            </div>

            {{-- (timeline lives inside the card's lifecycle box) --}}

            {{-- actions: Pay (Stripe) + Download PDF side by side --}}
            <div class="mt-5">
                <div class="flex gap-3">
                    @if($invoice->status === 'paid')
                        <span class="flex-1 py-3.5 rounded-2xl text-center text-sm font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">✓ Paid{{ $invoice->paid_at ? ' · '.$invoice->paid_at->format('M j') : '' }}</span>
                    @elseif($invoice->isPayable() && $site->stripeReady())
                        {{-- POSTs to the public pay route → redirects to Stripe Checkout --}}
                        <form method="POST" action="{{ route('public.invoice.pay', [$site->name, $invoice->public_token]) }}" target="_blank" class="flex-1">
                            @csrf
                            <button type="submit"
                                    class="w-full py-3.5 rounded-2xl text-sm font-bold transition-transform hover:scale-[1.01]"
                                    style="background:var(--primary); color:var(--on-primary); box-shadow:0 10px 24px -8px color-mix(in srgb, var(--primary) 60%, transparent)">💳 Pay now</button>
                        </form>
                    @else
                        <span class="flex-1 py-3.5 rounded-2xl text-center text-sm font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-400"
                              title="{{ $site->stripeReady() ? 'Send the invoice first — drafts can\'t be paid.' : 'Connect Stripe to accept payments.' }}">Not payable yet</span>
                    @endif
                    <a href="{{ url($site->name.'/invoices/'.$invoice->id.'/pdf') }}"
                       data-download-progress data-filename="{{ $invoice->number }}.pdf" data-label="Preparing {{ $invoice->number }} PDF…"
                       class="flex-1 py-3.5 rounded-2xl text-center text-sm font-bold border-2 transition-colors text-gray-800 dark:text-gray-100 border-gray-300 dark:border-white/[0.15] hover:bg-white dark:hover:bg-white/[0.05]">⬇ Download PDF</a>
                </div>
                <p class="mt-2.5 text-center text-[11px] text-gray-400">
                    Pay now opens the secure Stripe checkout for this invoice — the same page the customer gets from their email.
                    · {{ $invoice->customer_email }}</p>
            </div>
        </div>
    </div>
</x-layouts.selected>
