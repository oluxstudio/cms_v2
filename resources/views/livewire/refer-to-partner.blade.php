@php
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $input = 'w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40';
    $label = 'block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1';
    $money = fn (int $c, ?string $cur = 'gbp') => \App\Support\Money::format($c, $cur ?: 'gbp');
    $cutOf = fn (int $c) => (int) round($c * $cutPct / 100);
    $ready = $canManage && $planAllows && $isMember;
@endphp
<div class="relative z-[60]">
@if ($open)
    <x-lightbox close="close" icon="🤝" title="Refer to a partner" subtitle="Pass this customer to a trusted local business — with their consent." max-width="max-w-lg">

        @if ($sentReference)
            <div class="text-center py-6" data-referral-sent>
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-emerald-50 dark:bg-emerald-500/10 text-2xl">✅</span>
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Referral {{ $sentReference }} sent</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                    {{ $consentMethod === 'form' ? 'The customer already agreed, so the partner has their details now.' : 'We\'ve emailed the customer to ask if they\'re happy to be passed on. The partner only sees their details once they say yes.' }}
                </p>
                <a href="{{ route('site.network', ['siteID' => $site->name, 'tab' => 'sent']) }}" wire:navigate class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">View sent referrals</a>
            </div>
        @elseif (! $ready)
            <div class="text-center py-6" data-referral-blocked>
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-amber-50 dark:bg-amber-500/10 text-2xl">🔒</span>
                @if (! $canManage)
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">You can't send referrals</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Ask an owner to give you the “network.manage” permission.</p>
                @elseif (! $planAllows)
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">Referrals need a paid plan</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Upgrade to send customers to partners and earn when they win the work.</p>
                    <a href="{{ route('account.subscription') }}" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">See plans</a>
                @else
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">Join the referral network first</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">It takes a minute — set your profile and agree to the network terms.</p>
                    <a href="{{ route('site.network', $site->name) }}" wire:navigate class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Open the network</a>
                @endif
            </div>
        @else
            <div class="space-y-4">
                {{-- Customer --}}
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}" for="rtp-name">Customer name</label>
                        <input id="rtp-name" type="text" wire:model="name" class="{{ $input }}">
                        @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="rtp-email">Email</label>
                        <input id="rtp-email" type="email" wire:model="email" class="{{ $input }}">
                        @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="rtp-phone">Phone <span class="font-normal">(optional)</span></label>
                        <input id="rtp-phone" type="tel" wire:model="phone" class="{{ $input }}">
                        @error('phone')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Partner --}}
                <div>
                    <span class="{{ $label }}">Partner</span>
                    @if ($partner)
                        <div class="flex items-start gap-3 rounded-2xl px-4 py-3 border border-gray-100 dark:border-white/[0.06] bg-gray-50 dark:bg-white/[0.03]">
                            <div class="min-w-0 flex-1">
                                <p class="text-[14px] font-bold text-gray-900 dark:text-white truncate">{{ \App\Livewire\NetworkPage::siteLabel($partner->site) }}</p>
                                <p class="text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ $partner->business_type }}{{ $partner->area ? ' · '.$partner->area : '' }}</p>
                            </div>
                            <button type="button" wire:click="clearPartner" class="{{ $btnSolid }} text-[11px] px-2.5 py-1 shrink-0">Change</button>
                        </div>
                        <div class="mt-2 rounded-2xl px-4 py-3 bg-emerald-50 dark:bg-emerald-500/10 text-[12.5px] text-emerald-900 dark:text-emerald-100" data-partner-fee>
                            {{ \App\Livewire\NetworkPage::siteLabel($partner->site) }} pays <b>{{ $money((int) $partner->fee_cents, $partner->currency) }}</b> if this customer becomes theirs —
                            you earn <b>{{ $money((int) $partner->fee_cents - $cutOf((int) $partner->fee_cents), $partner->currency) }}</b> after Olux's {{ rtrim(rtrim(number_format($cutPct, 2), '0'), '.') }}%.
                        </div>
                    @else
                        <input type="search" wire:model.live.debounce.300ms="partnerSearch" class="{{ $input }}" placeholder="Search partners by service, trade or area…">
                        <div class="mt-2 max-h-56 overflow-y-auto space-y-1.5">
                            @forelse ($partners as $p)
                                <button type="button" wire:click="choosePartner('{{ $p->site_id }}')" wire:key="rtp-{{ $p->id }}"
                                        class="w-full text-left flex items-center gap-3 rounded-xl px-3 py-2 border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-[#1d1e2a] hover:bg-gray-50 dark:hover:bg-white/[0.04]">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-semibold text-gray-900 dark:text-white truncate">{{ \App\Livewire\NetworkPage::siteLabel($p->site) }}</span>
                                        <span class="block text-[11px] text-gray-400 truncate">{{ $p->business_type }}{{ $p->area ? ' · '.$p->area : '' }}</span>
                                    </span>
                                    <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 shrink-0">{{ $money((int) $p->fee_cents, $p->currency) }}</span>
                                </button>
                            @empty
                                <p class="text-[12.5px] text-gray-400 text-center py-4">No partners match{{ $partnerSearch ? ' “'.$partnerSearch.'”' : '' }}.</p>
                            @endforelse
                        </div>
                    @endif
                    @error('toSiteId')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $label }}" for="rtp-note">Note for the partner <span class="font-normal">(optional)</span></label>
                    <textarea id="rtp-note" wire:model="note" rows="3" maxlength="1000" class="{{ $input }} resize-y" placeholder="What does the customer need?"></textarea>
                    @error('note')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Consent --}}
                <fieldset>
                    <legend class="{{ $label }}">Customer consent</legend>
                    <div class="space-y-1.5">
                        <label class="flex items-start gap-2.5 rounded-xl px-3 py-2.5 border cursor-pointer {{ $consentMethod === 'email' ? 'border-indigo-300 dark:border-indigo-500/40 bg-indigo-50/50 dark:bg-indigo-500/[0.06]' : 'border-gray-100 dark:border-white/[0.06]' }}">
                            <input type="radio" wire:model.live="consentMethod" value="email" class="mt-0.5">
                            <span><span class="block text-[13px] font-semibold text-gray-900 dark:text-white">Send the customer a consent email</span>
                                <span class="block text-[11.5px] text-gray-500 dark:text-gray-400">Recommended. Their details are shared only after they click Yes.</span></span>
                        </label>
                        <label class="flex items-start gap-2.5 rounded-xl px-3 py-2.5 border cursor-pointer {{ $consentMethod === 'form' ? 'border-indigo-300 dark:border-indigo-500/40 bg-indigo-50/50 dark:bg-indigo-500/[0.06]' : 'border-gray-100 dark:border-white/[0.06]' }}">
                            <input type="radio" wire:model.live="consentMethod" value="form" class="mt-0.5">
                            <span><span class="block text-[13px] font-semibold text-gray-900 dark:text-white">The customer already ticked consent on our form</span>
                                <span class="block text-[11.5px] text-gray-500 dark:text-gray-400">Shared straight away. Only choose this if they really did.</span></span>
                        </label>
                    </div>
                    @error('consentMethod')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    @if ($consentMethod === 'form' && $partner?->site)
                        <p class="mt-2 rounded-xl px-3 py-2 bg-gray-50 dark:bg-white/[0.04] text-[11.5px] text-gray-600 dark:text-gray-300" data-consent-text>
                            They must have agreed to: “{{ \App\Modules\Network\ReferralManager::consentText($site, $partner->site, filled($phone)) }}”
                        </p>
                    @endif
                </fieldset>

                <p class="text-[11.5px] text-gray-400">The customer is always asked first — nothing is passed on without their agreement.</p>
                @error('refer')<p class="text-sm font-semibold text-rose-600 dark:text-rose-400" role="alert">{{ $message }}</p>@enderror
            </div>
        @endif

        @if ($ready && ! $sentReference)
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="close" class="{{ $btnSolid }} text-sm px-4 py-2">Cancel</button>
                    <button type="button" wire:click="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 text-sm font-bold px-4 py-2 rounded-xl" style="background:var(--primary);color:var(--on-primary)">
                        {{ $consentMethod === 'form' ? 'Send referral' : 'Ask customer & refer' }}
                    </button>
                </div>
            </x-slot:footer>
        @endif
    </x-lightbox>
@endif
</div>
