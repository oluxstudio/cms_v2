@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btn = 'fx inline-flex items-center justify-center min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold';
    $btnOutline = $btn.' border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
    $statePill = fn (string $s) => match ($s) {
        'active' => ['#dcfce7', '#15803d', 'Showing now'],
        'scheduled' => ['#e0f2fe', '#0369a1', 'Scheduled'],
        default => ['#f3f4f6', '#374151', 'Ended'],
    };
    $audienceText = fn ($a) => match ($a->audience) {
        'plans' => 'Plans: '.collect((array) $a->plans)->map(fn ($p) => $plans[$p] ?? $p)->implode(', '),
        'accounts' => count((array) $a->account_ids).' chosen '.Str::plural('account', count((array) $a->account_ids)),
        default => 'Everyone',
    };
@endphp

<x-tri-layout title="Announcements" subtitle="Messages shown across the app to everyone, to chosen plans, or to specific accounts."
    :labels="['📊 Numbers', '📣 Announcements', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <button wire:click="create" class="fx inline-flex items-center gap-1.5 min-h-[40px] px-4 rounded-full text-sm font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New announcement
        </button>
    </x-slot:header>

    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$stats['active']" label="Showing now" :sub="number_format($stats['reach']).' accounts reached'"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
        <x-tile accent="sky" :value="$stats['scheduled']" label="Scheduled" sub="start later"
                icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </div>
    </x-slot:rail>

    <div class="@container max-w-[52rem] mx-auto space-y-3">
        @forelse ($rows as $r)
            @php $a = $r['a']; [$pb, $pf, $pl] = $statePill($r['state']); @endphp
            <div class="{{ $panel }} p-5 {{ $r['state'] === 'ended' ? 'opacity-70' : '' }}">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                    <span class="text-[12px] text-gray-600 dark:text-gray-300">{{ $audienceText($a) }} · {{ number_format($r['reach']) }} accounts
                        @if ($a->dismissible) · {{ $r['dismissed'] }} dismissed @endif
                        @if ($a->alerts_sent_at) · in notifications @endif</span>
                    <span class="ml-auto flex items-center gap-2">
                        @if ($r['state'] !== 'ended')
                            <button wire:click="endNow('{{ $a->id }}')" data-confirm="Stop showing this announcement now?" class="{{ $btnOutline }}">End now</button>
                        @endif
                        <button wire:click="edit('{{ $a->id }}')" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Edit</button>
                    </span>
                </div>
                <x-announcement-item :a="$a" preview />
                <p class="mt-2 text-[11.5px] text-gray-500 dark:text-gray-400">
                    {{ $a->starts_at ? 'From '.$a->starts_at->format('j M Y H:i') : 'From when it was saved' }}
                    · {{ $a->ends_at ? 'until '.$a->ends_at->format('j M Y H:i') : 'no end date' }}
                    · created {{ $a->created_at->diffForHumans() }}
                </p>
            </div>
        @empty
            <div class="{{ $panel }} p-10 text-center">
                <p class="text-sm font-bold text-gray-800 dark:text-gray-100">No announcements yet</p>
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400 mt-1">Use them for maintenance windows, new features or plan news.</p>
            </div>
        @endforelse
    </div>

    <x-slot:quick>
        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">Where they appear</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 list-disc ml-4">
                <li>As a banner at the top of every signed-in page, for the chosen audience.</li>
                <li>Optionally also in each of their sites' notifications (the bell), so it stays after the banner ends.</li>
                <li>Critical banners are red; people can dismiss a banner unless you turn that off.</li>
            </ul>
        </div>
    </x-slot:quick>

    @if ($editing !== null)
        <x-side-drawer close="close" width="max-w-xl">
            <x-slot:header>
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">{{ $editing === '' ? 'New announcement' : 'Edit announcement' }}</p>
                <h2 class="font-display text-xl font-bold text-gray-900 dark:text-white truncate">{{ $form['title'] ?: 'Untitled' }}</h2>
            </x-slot:header>
            <form id="announce-form" wire:submit="save" class="p-6 space-y-5">
                @if ($preview)
                    <div>
                        <p class="bkf-label">Preview</p>
                        <x-announcement-item :a="$preview" preview />
                    </div>
                @endif
                <label class="block">
                    <span class="bkf-label">Title</span>
                    <input type="text" wire:model.live.debounce.300ms="form.title" maxlength="140" placeholder="Scheduled maintenance on Sunday" class="bkf-input w-full">
                    @error('form.title')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                </label>
                <label class="block">
                    <span class="bkf-label">Message <span class="font-normal text-gray-500">(optional)</span></span>
                    <textarea wire:model.live.debounce.400ms="form.body" rows="3" maxlength="500" class="bkf-input w-full" placeholder="The editor will be unavailable from 22:00 to 23:00."></textarea>
                </label>
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="bkf-label">Link <span class="font-normal text-gray-500">(optional)</span></span>
                        <input type="url" wire:model.live.debounce.400ms="form.link_url" placeholder="https://…" class="bkf-input w-full">
                        @error('form.link_url')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="bkf-label">Link text</span>
                        <input type="text" wire:model.live.debounce.400ms="form.link_label" maxlength="40" placeholder="Learn more" class="bkf-input w-full">
                    </label>
                </div>
                <fieldset>
                    <legend class="bkf-label">Importance</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['info' => 'Information', 'warning' => 'Warning', 'critical' => 'Critical'] as $lv => $ll)
                            <label class="flex items-center gap-2 rounded-xl border px-3 py-2 cursor-pointer text-[13px] font-semibold {{ $form['level'] === $lv ? '' : 'border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200' }}"
                                   @if ($form['level'] === $lv) style="border-color:var(--primary)" @endif>
                                <input type="radio" wire:model.live="form.level" value="{{ $lv }}"> {{ $ll }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="rounded-2xl border border-gray-200 dark:border-white/[0.1] p-4 space-y-3">
                    <legend class="px-1 text-[12px] font-bold text-gray-700 dark:text-gray-200">Who sees it</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['all' => 'Everyone', 'plans' => 'Chosen plans', 'accounts' => 'Specific accounts'] as $au => $al)
                            <label class="flex items-center gap-2 rounded-xl border px-3 py-2 cursor-pointer text-[13px] font-semibold {{ $form['audience'] === $au ? '' : 'border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200' }}"
                                   @if ($form['audience'] === $au) style="border-color:var(--primary)" @endif>
                                <input type="radio" wire:model.live="form.audience" value="{{ $au }}"> {{ $al }}
                            </label>
                        @endforeach
                    </div>
                    @if ($form['audience'] === 'plans')
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($plans as $pk => $pn)
                                <label class="bkf-check"><input type="checkbox" wire:model="form.plans" value="{{ $pk }}"> {{ $pn }}</label>
                            @endforeach
                        </div>
                        @error('form.plans')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    @elseif ($form['audience'] === 'accounts')
                        <label class="block">
                            <span class="bkf-label">Account emails (one per line)</span>
                            <textarea wire:model="form.emails" rows="4" class="bkf-input w-full font-mono text-[13px]" placeholder="client@example.com"></textarea>
                            @error('form.emails')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    @endif
                </fieldset>

                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="bkf-label">Start <span class="font-normal text-gray-500">(empty = now)</span></span>
                        <input type="datetime-local" wire:model="form.starts_at" class="bkf-input w-full">
                        @error('form.starts_at')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="bkf-label">End <span class="font-normal text-gray-500">(empty = until ended)</span></span>
                        <input type="datetime-local" wire:model="form.ends_at" class="bkf-input w-full">
                        @error('form.ends_at')<span class="text-[12px] font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <x-field.toggle model="form.dismissible" text="People can dismiss it" live />
                    <x-field.toggle model="form.add_to_alerts" text="Also add to notifications" />
                </div>

                @if ($editing !== '')
                    <button type="button" wire:click="delete('{{ $editing }}')" data-confirm="Delete this announcement?" class="{{ $btn }} bg-rose-600 text-white">Delete announcement</button>
                @endif
            </form>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="close" class="{{ $btnOutline }}">Cancel</button>
                    <button type="submit" form="announce-form" class="{{ $btn }} min-w-[7rem]" style="background:var(--primary);color:var(--on-primary)">
                        <span wire:loading.remove wire:target="save">{{ $editing === '' ? 'Publish' : 'Save' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </x-slot:footer>
        </x-side-drawer>
    @endif
</x-tri-layout>
