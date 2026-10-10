@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $input = 'w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40';
    $starsHtml = fn (int $n, string $size = 'text-[15px]') => '<span class="'.$size.' tracking-[2px] leading-none whitespace-nowrap" aria-label="'.$n.' out of 5 stars"><span class="text-amber-400">'.str_repeat('★', $n).'</span><span class="text-gray-200 dark:text-gray-700">'.str_repeat('★', 5 - $n).'</span></span>';
    $avg = $aggregate['average'];
    $avgStars = $avg ? str_repeat('★', (int) round($avg)).str_repeat('☆', 5 - (int) round($avg)) : null;
    $statusClass = [
        'pending' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300',
        'published' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'hidden' => 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400',
    ];
    $sourceClass = [
        'on_site' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300',
        'request' => 'bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300',
        'manual' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300',
        'import' => 'bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300',
    ];
    $filters = [
        'all' => ['All', $stats['total']],
        'pending' => ['Pending', $stats['pending']],
        'published' => ['Published', $stats['published']],
        'hidden' => ['Hidden', $stats['hidden']],
        'featured' => ['Featured', $stats['featured']],
    ];
    $reqState = [
        'completed' => ['Completed', 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'],
        'opened' => ['Opened', 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300'],
        'sent' => ['Sent', 'bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300'],
        'draft' => ['Not sent', 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300'],
    ];
    $trend = ($stats['recentAvg'] !== null && $stats['prevAvg'] !== null) ? round($stats['recentAvg'] - $stats['prevAvg'], 1) : null;
@endphp
<x-tri-layout title="Reviews" subtitle="Collect, approve and show customer reviews — with Google-friendly review markup." :site-name="$site->name"
    :labels="['📊 Overview', '⭐ Reviews', '⚡ Quick access']">

    {{-- ── LEFT rail: reviews at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$avg !== null ? number_format($avg, 1).' / 5' : '—'" label="Average rating"
                :sub="$avgStars ?? 'no published reviews yet'"
                icon="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.72-2.89a.56.56 0 00-.59 0l-4.72 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.35L11.48 3.5z" />
        <x-tile accent="lime" :value="number_format($stats['total'])" label="Total reviews" :sub="$stats['published'].' published'"
                icon="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        <x-tile accent="{{ $stats['pending'] ? 'rose' : 'sky' }}" :value="$stats['pending']" label="Awaiting approval" :sub="$stats['pending'] ? 'needs a decision' : 'all caught up'"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lavender" :value="$stats['thisMonth']" label="New this month" :sub="now()->format('F')"
                icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        <x-tile accent="cocoa" :value="$stats['responseRate'].'%'" label="Response rate" :sub="$stats['replied'].' replied'"
                icon="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
        <x-tile accent="sky" :value="$stats['reqSent'].' / '.$stats['reqCompleted']" label="Requests sent / completed"
                :sub="$stats['reqSent'] ? round($stats['reqCompleted'] / $stats['reqSent'] * 100).'% completed' : 'none sent yet'"
                icon="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </div>
    </x-slot:rail>

    {{-- ── CENTRE: Reviews / Requests ── --}}
    <div class="space-y-5 max-w-[52rem]" x-data="{ tab: $wire.entangle('tab').live }">
        <x-pill-tabs :tabs="['reviews' => 'Reviews', 'requests' => 'Requests']" :dots="$stats['pending'] ? ['reviews'] : []" class="!mb-0" />

        @if ($notice)
            <div class="flex items-center justify-between gap-3 rounded-2xl px-4 py-3 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-200 text-sm font-semibold">
                <span>{{ $notice }}</span>
                <button type="button" wire:click="$set('notice', '')" class="text-xs opacity-70 hover:opacity-100">✕</button>
            </div>
        @endif

    @if ($tab === 'reviews')
        @if ($showAdd)
        {{-- ── Add a review by hand ── --}}
        <div class="{{ $panel }} p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Add a review manually</p>
                    <p class="text-xs text-gray-400">For reviews you received elsewhere (by email, in person). Published straight away.</p>
                </div>
                <button type="button" wire:click="cancelAdd" class="text-xs font-semibold text-gray-400 hover:text-gray-600">✕ Close</button>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Name</label>
                    <input type="text" wire:model="m.name" class="{{ $input }}" placeholder="Jane D.">
                    @error('m.name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Email <span class="font-normal">(private, optional)</span></label>
                    <input type="email" wire:model="m.email" class="{{ $input }}">
                    @error('m.email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Rating</span>
                    <div class="flex gap-0.5">
                        @for ($s = 1; $s <= 5; $s++)
                            <button type="button" wire:click="$set('m.rating', {{ $s }})" aria-label="{{ $s }} stars"
                                    class="text-2xl leading-none {{ (int) $m['rating'] >= $s ? 'text-amber-400' : 'text-gray-200 dark:text-gray-700' }}">★</button>
                        @endfor
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Date</label>
                    <input type="date" wire:model="m.date" max="{{ now()->toDateString() }}" class="{{ $input }} !w-auto">
                    @error('m.date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Headline <span class="font-normal">(optional)</span></label>
                <input type="text" wire:model="m.title" class="{{ $input }}">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Review</label>
                <textarea wire:model="m.body" rows="4" class="{{ $input }} resize-y"></textarea>
                @error('m.body')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Photo <span class="font-normal">(optional)</span></span>
                <x-asset-picker model="m.photo" :site="$site" type="image" placeholder="Photo URL, or pick from assets" />
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" wire:click="cancelAdd" class="{{ $btnSolid }} text-sm px-4 py-2">Cancel</button>
                <button type="button" wire:click="saveManual" class="inline-flex items-center gap-1.5 text-sm font-bold px-4 py-2 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Save review</button>
            </div>
        </div>
        @endif

        {{-- ── Toolbar: search · filters · stars · sort · layout · add ── --}}
        <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[12rem]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <x-field.text wire:model.live.debounce.250ms="search" placeholder="Search name, headline or words…" class="w-full" style="padding-left:2.25rem" />
                </div>
                <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                    <option value="highest">Highest rated</option>
                    <option value="lowest">Lowest rated</option>
                </select>
                <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
                @if ($canManage)
                    <button type="button" wire:click="openAdd"
                            class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add review
                    </button>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex gap-2 overflow-x-auto no-scrollbar">
                    @foreach ($filters as $key => [$label, $n])
                        <button type="button" wire:click="setFilter('{{ $key }}')"
                            class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                                {{ $filter === $key
                                    ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                                    : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                            {{ $label }} <span class="opacity-60">{{ $n }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="flex items-center gap-1 ml-auto" title="Show only this many stars">
                    @foreach ([5, 4, 3, 2, 1] as $s)
                        <button type="button" wire:click="setStars({{ $s }})"
                                class="px-2 py-1 rounded-lg text-[12px] font-bold border transition-colors
                                    {{ $stars === $s ? 'bg-amber-400 text-gray-900 border-amber-400' : 'bg-white dark:bg-[#1d1e2a] text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                            {{ $s }}★
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($reviews->isEmpty())
            <div class="{{ $panel }} px-6 py-16 text-center">
                <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-amber-50 dark:bg-amber-500/10 text-2xl">⭐</span>
                @if ($stats['total'] === 0)
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">No reviews yet</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Ask your customers for one from the Requests tab, or let visitors leave one on your site.</p>
                    @if ($canManage)
                        <button type="button" wire:click="setTab('requests')" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Request a review</button>
                    @endif
                @else
                    <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try another search, filter or star rating.</p>
                    <button type="button" x-on:click="$wire.set('search', ''); $wire.set('stars', 0); $wire.setFilter('all')" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all reviews</button>
                @endif
            </div>
        @elseif ($viewMode === 'grid')
            {{-- ── Cards ── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($reviews as $r)
                    @php $img = $photo($r->photo); @endphp
                    <div class="flex flex-col {{ $panel }} !rounded-2xl overflow-hidden {{ $r->status === 'pending' ? 'ring-2 ring-rose-200 dark:ring-rose-500/30' : '' }}" wire:key="rv-{{ $r->id }}">
                        <div class="p-5 flex-1">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        {!! $starsHtml($r->rating) !!}
                                        @if ($r->featured)<span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-400 text-gray-900">Featured</span>@endif
                                    </div>
                                    @if ($r->title)<p class="mt-2 text-[15px] font-bold text-gray-900 dark:text-white line-clamp-1">{{ $r->title }}</p>@endif
                                    <p class="mt-1 text-[13px] text-gray-600 dark:text-gray-300 line-clamp-4 whitespace-pre-line">{{ $r->body }}</p>
                                </div>
                                @if ($img)
                                    <img src="{{ $img }}" alt="" loading="lazy" class="w-16 h-16 rounded-xl object-cover shrink-0 bg-gray-100 dark:bg-white/[0.06]">
                                @endif
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[12px]">
                                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $r->name }}</span>
                                <span class="text-gray-400">· {{ $r->created_at->format('j M Y') }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $sourceClass[$r->source] ?? $sourceClass['import'] }}">{{ $r->sourceLabel() }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusClass[$r->status] ?? '' }}">{{ ucfirst($r->status) }}</span>
                            </div>

                            @if ($replyingId === $r->id)
                                <div class="mt-3 space-y-2">
                                    <textarea wire:model="replyBody" rows="3" class="{{ $input }} resize-y" placeholder="Write a public reply…"></textarea>
                                    @error('replyBody')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                                    <div class="flex gap-2 justify-end">
                                        <button type="button" wire:click="cancelReply" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Cancel</button>
                                        <button type="button" wire:click="saveReply" class="px-3 py-1.5 rounded-lg text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">Save reply</button>
                                    </div>
                                </div>
                            @elseif ($r->reply_body)
                                <div class="mt-3 rounded-xl px-3 py-2 bg-gray-50 dark:bg-white/[0.04] border-l-2" style="border-color:var(--primary)">
                                    <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400">Your reply · {{ $r->replied_at?->format('j M Y') }}</p>
                                    <p class="text-[12.5px] text-gray-600 dark:text-gray-300 line-clamp-3 whitespace-pre-line">{{ $r->reply_body }}</p>
                                </div>
                            @endif
                        </div>
                        @if ($canManage)
                        <div class="flex flex-wrap items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                            @if ($r->status !== 'published')
                                <button type="button" wire:click="approve('{{ $r->id }}')" class="px-3 py-1.5 rounded-lg text-[12px] font-bold bg-emerald-600 text-white hover:bg-emerald-700">Approve</button>
                            @endif
                            @if ($r->status !== 'hidden')
                                <button type="button" wire:click="hide('{{ $r->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Hide</button>
                            @endif
                            <button type="button" wire:click="toggleFeature('{{ $r->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">{{ $r->featured ? 'Unfeature' : '★ Feature' }}</button>
                            <button type="button" wire:click="startReply('{{ $r->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">{{ $r->reply_body ? 'Edit reply' : 'Reply' }}</button>
                            <button type="button" wire:click="deleteReview('{{ $r->id }}')" data-confirm="Delete this review from {{ $r->name }}? This can't be undone." title="Delete"
                                    class="ml-auto p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            {{-- ── List & Compact (table) ── --}}
            @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
            <div class="{{ $panel }} !rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <th class="px-4 py-3">Rating</th>
                                <th class="px-4 py-3">Review</th>
                                @unless ($compact)<th class="px-4 py-3">Source</th>@endunless
                                <th class="px-4 py-3">Status</th>
                                <th class="w-40 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            @foreach ($reviews as $r)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] align-top" wire:key="rvr-{{ $r->id }}">
                                    <td class="{{ $pad }}">{!! $starsHtml($r->rating, 'text-[13px]') !!}</td>
                                    <td class="{{ $pad }} min-w-[14rem]">
                                        <p class="font-semibold text-gray-900 dark:text-white truncate max-w-[22rem]">{{ $r->title ?: \Illuminate\Support\Str::limit($r->body, 60) }}</p>
                                        @unless ($compact)<p class="text-[12px] text-gray-500 dark:text-gray-400 line-clamp-2 max-w-[22rem]">{{ $r->body }}</p>@endunless
                                        <p class="text-[11px] text-gray-400">{{ $r->name }} · {{ $r->created_at->format('j M Y') }}{{ $r->featured ? ' · Featured' : '' }}{{ $r->reply_body ? ' · Replied' : '' }}</p>
                                        @if ($replyingId === $r->id)
                                            <div class="mt-2 space-y-2">
                                                <textarea wire:model="replyBody" rows="3" class="{{ $input }} resize-y" placeholder="Write a public reply…"></textarea>
                                                <div class="flex gap-2">
                                                    <button type="button" wire:click="cancelReply" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Cancel</button>
                                                    <button type="button" wire:click="saveReply" class="px-3 py-1.5 rounded-lg text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">Save reply</button>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                    @unless ($compact)<td class="{{ $pad }}"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $sourceClass[$r->source] ?? $sourceClass['import'] }}">{{ $r->sourceLabel() }}</span></td>@endunless
                                    <td class="{{ $pad }}"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusClass[$r->status] ?? '' }}">{{ ucfirst($r->status) }}</span></td>
                                    <td class="{{ $pad }}">
                                        @if ($canManage)
                                        <div class="flex items-center gap-1 justify-end whitespace-nowrap">
                                            @if ($r->status !== 'published')
                                                <button type="button" wire:click="approve('{{ $r->id }}')" title="Approve" class="px-2 py-1 rounded-lg text-[11px] font-bold bg-emerald-600 text-white">✓</button>
                                            @endif
                                            @if ($r->status !== 'hidden')
                                                <button type="button" wire:click="hide('{{ $r->id }}')" title="Hide" class="{{ $btnSolid }} text-[11px] px-2 py-1">Hide</button>
                                            @endif
                                            <button type="button" wire:click="toggleFeature('{{ $r->id }}')" title="{{ $r->featured ? 'Unfeature' : 'Feature' }}" class="{{ $btnSolid }} text-[11px] px-2 py-1 {{ $r->featured ? '!text-amber-500' : '' }}">★</button>
                                            <button type="button" wire:click="startReply('{{ $r->id }}')" title="Reply" class="{{ $btnSolid }} text-[11px] px-2 py-1">↩</button>
                                            <button type="button" wire:click="deleteReview('{{ $r->id }}')" data-confirm="Delete this review from {{ $r->name }}? This can't be undone." title="Delete"
                                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($hasMore)
            <div class="text-center">
                <button type="button" wire:click="loadMore" class="{{ $btnSolid }} text-sm px-5 py-2">Show more reviews</button>
            </div>
        @endif
    @else
        {{-- ══ Requests ══ --}}
        @if ($canManage)
        <div class="grid sm:grid-cols-2 gap-4">
            {{-- One person --}}
            <div class="{{ $panel }} p-5 space-y-3">
                <div>
                    <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Ask one person</p>
                    <p class="text-xs text-gray-400">They get an email with a private link to leave a review.</p>
                </div>
                <div>
                    <input type="text" wire:model="rqName" class="{{ $input }}" placeholder="Name (optional)">
                    @error('rqName')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <input type="email" wire:model="rqEmail" class="{{ $input }}" placeholder="customer@example.com">
                    @error('rqEmail')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="button" wire:click="sendOne" wire:loading.attr="disabled" class="w-full text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Send request</button>
            </div>

            {{-- Paste emails --}}
            <div class="{{ $panel }} p-5 space-y-3">
                <div>
                    <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Paste email addresses</p>
                    <p class="text-xs text-gray-400">Any separator — commas, spaces or one per line. Up to 200 at once.</p>
                </div>
                <textarea wire:model="rqPaste" rows="4" class="{{ $input }} resize-y" placeholder="ana@example.com, ben@example.com"></textarea>
                @error('rqPaste')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                <button type="button" wire:click="sendBulk" wire:loading.attr="disabled" class="{{ $btnSolid }} w-full text-sm px-4 py-2.5">
                    Send to pasted{{ count($pickedContacts) ? ' + '.count($pickedContacts).' picked' : '' }}
                </button>
            </div>
        </div>

        {{-- From contacts --}}
        <div class="{{ $panel }} p-5 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Choose from your contacts</p>
                    <p class="text-xs text-gray-400">{{ count($pickedContacts) }} picked</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="search" wire:model.live.debounce.300ms="contactSearch" class="{{ $input }} !w-48" placeholder="Search contacts…">
                    <button type="button" wire:click="sendBulk" @disabled(! count($pickedContacts)) class="text-sm font-bold px-4 py-2 rounded-xl disabled:opacity-40" style="background:var(--primary);color:var(--on-primary)">Send to picked</button>
                </div>
            </div>
            @if ($contacts->isEmpty())
                <p class="text-sm text-gray-400 py-4 text-center">No contacts with an email address{{ $contactSearch ? ' match “'.$contactSearch.'”' : ' yet' }}.</p>
            @else
                <div class="grid sm:grid-cols-2 gap-1.5 max-h-72 overflow-y-auto">
                    @foreach ($contacts as $c)
                        <label class="flex items-center gap-2.5 px-3 py-2 rounded-xl cursor-pointer border border-gray-100 dark:border-white/[0.06] hover:bg-gray-50 dark:hover:bg-white/[0.03]" wire:key="ct-{{ $c->id }}">
                            <input type="checkbox" wire:model.live="pickedContacts" value="{{ $c->id }}" class="rounded border-gray-300 dark:border-white/20">
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $c->name ?: $c->email }}</span>
                                <span class="block text-[11px] text-gray-400 truncate">{{ $c->email }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
        @endif

        {{-- Sent requests --}}
        <div class="{{ $panel }} !rounded-2xl overflow-hidden">
            <div class="px-5 pt-4 pb-2 flex items-center justify-between">
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white">Sent requests</p>
                <p class="text-xs text-gray-400">{{ $stats['reqOpened'] }} opened · {{ $stats['reqCompleted'] }} completed</p>
            </div>
            @if ($requests->isEmpty())
                <p class="px-5 pb-8 pt-4 text-sm text-gray-400 text-center">No requests sent yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <th class="px-4 py-2.5">Customer</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5">Sent</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            @foreach ($requests as $q)
                                @php [$stLabel, $stClass] = $reqState[$q->state()]; @endphp
                                <tr wire:key="rq-{{ $q->id }}">
                                    <td class="px-4 py-2.5">
                                        <span class="block font-semibold text-gray-900 dark:text-white truncate max-w-[16rem]">{{ $q->name ?: $q->email }}</span>
                                        @if ($q->name)<span class="block text-[11px] text-gray-400 truncate max-w-[16rem]">{{ $q->email }}</span>@endif
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $stClass }}">{{ $stLabel }}</span>
                                        @if ($q->reminder_sent_at && ! $q->completed_at)<span class="ml-1 text-[11px] text-gray-400">reminded</span>@endif
                                    </td>
                                    <td class="px-4 py-2.5 text-[12px] text-gray-400 whitespace-nowrap">{{ $q->sent_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($canManage)
                                        <div class="flex items-center gap-1 justify-end">
                                            @unless ($q->completed_at)
                                                <button type="button" wire:click="resend('{{ $q->id }}')" class="{{ $btnSolid }} text-[11px] px-2.5 py-1">Resend</button>
                                            @endunless
                                            <button type="button" wire:click="deleteRequest('{{ $q->id }}')" data-confirm="Delete the request to {{ $q->email }}? Its link will stop working." title="Delete"
                                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($moreRequests)
                    <div class="p-3 text-center border-t border-gray-100 dark:border-white/[0.05]">
                        <button type="button" wire:click="moreRequests" class="{{ $btnSolid }} text-[12px] px-4 py-1.5">Show more</button>
                    </div>
                @endif
            @endif
        </div>
    @endif
    </div>

    {{-- ══ RIGHT rail: summary · needs attention · on your site · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Rating summary</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $avg !== null ? number_format($avg, 1) : '—' }}</b> average ·
                <b class="text-gray-900 dark:text-white">{{ $aggregate['count'] }}</b> published
            </p>
            @php $maxN = max(1, max($aggregate['distribution'])); @endphp
            <div class="space-y-1.5">
                @foreach ($aggregate['distribution'] as $s => $n)
                    <button type="button" wire:click="setStars({{ $s }})" class="w-full flex items-center gap-2 text-[12.5px] group" title="Show {{ $s }}-star reviews">
                        <span class="w-7 shrink-0 text-gray-600 dark:text-gray-300 font-semibold">{{ $s }}★</span>
                        <span class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                            <span class="block h-full rounded-full bg-amber-400" style="width:{{ round($n / $maxN * 100) }}%"></span>
                        </span>
                        <span class="w-7 shrink-0 text-right font-bold text-gray-900 dark:text-white tabular-nums">{{ $n }}</span>
                    </button>
                @endforeach
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-white/[0.06] text-[12.5px]">
                <p class="text-gray-500 dark:text-gray-400">Last 30 days</p>
                <p class="font-bold text-gray-900 dark:text-white">
                    {{ $stats['recentAvg'] !== null ? number_format($stats['recentAvg'], 1).' avg from '.$stats['recentN'] : 'No new published reviews' }}
                    @if ($trend !== null)
                        <span class="ml-1 text-[11px] font-bold {{ $trend > 0 ? 'text-emerald-600' : ($trend < 0 ? 'text-rose-600' : 'text-gray-400') }}">
                            {{ $trend > 0 ? '▲ +'.$trend : ($trend < 0 ? '▼ '.$trend : '— steady') }} vs previous 30
                        </span>
                    @endif
                </p>
            </div>
        </div>

        @if ($pendingList->isNotEmpty() || $lowUnanswered->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @if ($pendingList->isNotEmpty())
                    <button type="button" wire:click="showPending" class="w-full text-left rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10 hover:ring-2 hover:ring-rose-200 dark:hover:ring-rose-500/30">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $stats['pending'] }} {{ Str::plural('review', $stats['pending']) }} awaiting approval</p>
                        @foreach ($pendingList as $p)
                            <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70 truncate">{{ str_repeat('★', $p->rating) }} {{ $p->name }} — {{ $p->title ?: \Illuminate\Support\Str::limit($p->body, 40) }}</p>
                        @endforeach
                    </button>
                @endif
                @if ($lowUnanswered->isNotEmpty())
                    <div class="rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">Low ratings without a reply</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70 mb-1.5">A calm, helpful reply reassures future customers.</p>
                        @foreach ($lowUnanswered as $l)
                            @if ($canManage)
                                <button type="button" wire:click="replyTo('{{ $l->id }}')" class="block w-full text-left text-[12px] font-semibold text-amber-800 dark:text-amber-200 hover:underline truncate">↩ {{ $l->rating }}★ {{ $l->name }} — {{ $l->title ?: \Illuminate\Support\Str::limit($l->body, 36) }}</button>
                            @else
                                <p class="text-[12px] text-amber-800 dark:text-amber-200 truncate">{{ $l->rating }}★ {{ $l->name }}</p>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5" x-data="{ copied: '' }">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-1">Show reviews on your site</p>
            <p class="text-[12px] text-gray-500 dark:text-gray-400 mb-3">Templates read <code class="font-mono text-[11px]">/api/sites/{{ $site->name }}/reviews</code> — published reviews only, with the average, star breakdown and Google review markup.</p>
            <div class="flex items-center gap-1.5 mb-3">
                <input type="text" readonly value="{{ $apiUrl }}" x-ref="api" class="{{ $input }} !text-[11px] font-mono">
                <button type="button" x-on:click="navigator.clipboard.writeText($refs.api.value); copied = 'api'; setTimeout(() => copied = '', 1500)" class="{{ $btnSolid }} text-[11px] px-2.5 py-2 shrink-0" x-text="copied === 'api' ? 'Copied' : 'Copy'">Copy</button>
            </div>
            <div class="flex items-center justify-between mb-1">
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">JSON-LD snippet</p>
                <button type="button" x-on:click="navigator.clipboard.writeText($refs.ld.textContent); copied = 'ld'; setTimeout(() => copied = '', 1500)" class="{{ $btnSolid }} text-[11px] px-2.5 py-1" x-text="copied === 'ld' ? 'Copied' : 'Copy'">Copy</button>
            </div>
            <pre x-ref="ld" class="max-h-44 overflow-auto rounded-xl bg-gray-50 dark:bg-black/30 p-2.5 text-[10.5px] leading-snug font-mono text-gray-600 dark:text-gray-300 whitespace-pre-wrap break-all">{{ $ldSnippet }}</pre>
            <p class="text-[11px] text-gray-400 mt-2">Paste into a page's &lt;head&gt; on sites that don't use the API.</p>
        </div>

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['Contacts', 'People to ask', route('site.contacts', $site->name)],
                    ['Edit site', 'Place a reviews block', route('site.connect', $site->name)],
                    ['Site Properties', 'Name, address, email', route('site.properties', $site->name)],
                    ['Add-ons', 'Auto-publish & email text', route('site.marketplace', $site->name)],
                ] as [$label, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
