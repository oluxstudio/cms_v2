@php
    $customCount = count(array_filter($customized));
    $totalCount = count($customized);
@endphp
<x-tri-layout title="Emails" :subtitle="$tpl ? ($entry['label'].' — '.$entry['description']) : 'Every email this site sends — customise any of them.'" :site-name="$site->name"
    :labels="['📊 Overview', '✉️ Emails', '👁 Preview']" quick-width="lg:!w-[410px]">

    <x-slot:header>
        @if ($tpl)
            <button wire:click="backToList"
                    class="fx inline-flex items-center gap-1.5 min-h-[40px] px-3.5 rounded-xl text-[13px] font-bold border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                All templates
            </button>
        @endif
    </x-slot:header>

    {{-- ── LEFT rail: page stats as tiles ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3 mb-4">
        <x-tile accent="ink" wide :value="$customCount.' of '.$totalCount" label="Templates customised"
                icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                :sub="$customCount ? 'in your own words' : 'all on defaults'" />
        <x-tile accent="lime" :value="$logo ? 'Set' : 'Missing'" label="Logo"
                icon="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                :sub="$logo ? 'on every email' : 'add one below'" />
        @if ($tpl)
            <x-tile accent="lavender" :value="count(array_filter($sections, fn ($s) => $s['enabled'] ?? false))" label="Sections on"
                    icon="M4 6h16M4 10h16M4 14h16M4 18h16"
                    :sub="count($sections).' in the layout'" />
            <x-tile accent="sky" :value="$customized[$tpl] ? 'Customised' : 'Default'" label="This template"
                    icon="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                    :sub="$entry['group']" />
        @else
            <x-tile accent="lavender" :value="$totalCount" label="Email templates"
                    icon="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                    sub="every mail the site sends" />
            <x-tile accent="sky" :value="count($grouped)" label="Groups"
                    icon="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                    sub="forms · bookings · billing…" />
        @endif
    </div>

    {{-- Shared logo — appears on every branded email --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Logo — on every email</label>
        <div class="flex items-start gap-3">
            <div class="w-14 h-14 rounded-xl border border-gray-200 dark:border-white/[0.08] grid place-items-center overflow-hidden bg-gray-50 dark:bg-white/[0.04] shrink-0">
                @if($logo)<img src="{{ $logo }}" alt="logo" class="max-w-full max-h-full object-contain">@else<span class="text-xs text-gray-400">None</span>@endif
            </div>
            <div class="flex-1 min-w-0 space-y-2">
                <x-asset-picker model="logo" :site="$site" type="image" placeholder="Logo URL, or pick from assets" />
                <div class="flex items-center gap-3 flex-wrap">
                    <label class="text-xs font-semibold cursor-pointer" style="color:var(--primary)">
                        <span wire:loading.remove wire:target="logoUpload">⬆ Upload</span>
                        <span wire:loading wire:target="logoUpload">Uploading…</span>
                        <input type="file" wire:model="logoUpload" accept="image/*" class="hidden">
                    </label>
                    <button wire:click="saveLogo" class="text-xs font-semibold text-gray-500 hover:underline">Save</button>
                    @if($logo)<button wire:click="removeLogo" class="text-xs font-semibold text-rose-500 hover:text-rose-600">Remove</button>@endif
                </div>
            </div>
        </div>
        @error('logoUpload')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
    </div>
    </x-slot:rail>

{{-- ── CENTER ── --}}
<div class="max-w-[52rem] mx-auto">

    @if ($successMessage)
        <p class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-sm text-emerald-700 dark:text-emerald-400">{{ $successMessage }}</p>
    @endif

    @if (! $tpl)
        {{-- ══ LIST: every email template, grouped ══ --}}
        @foreach ($grouped as $groupLabel => $entries)
            <div class="mb-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">{{ $groupLabel }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ($entries as $key => $e)
                        <button wire:click="edit('{{ $key }}')"
                                class="fx text-left bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 hover:shadow-md hover:-translate-y-0.5 transition-all {{ $e['available'] ? '' : 'opacity-60' }}">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-extrabold text-gray-900 dark:text-white">{{ $e['label'] }}</span>
                                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full {{ $customized[$key] ? 'text-white' : 'bg-gray-100 dark:bg-white/[0.08] text-gray-500 dark:text-gray-400' }}"
                                      @if($customized[$key]) style="background:var(--primary)" @endif>
                                    {{ $customized[$key] ? 'Customised' : 'Default' }}
                                </span>
                            </span>
                            <span class="block text-[12px] text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">{{ $e['description'] }}</span>
                            @unless ($e['available'])
                                <span class="block text-[10.5px] text-amber-600 dark:text-amber-400 mt-1.5 font-semibold">Sent once the {{ $e['feature'] }} add-on is on — you can still customise it now.</span>
                            @endunless
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach

    @else
        {{-- ══ EDITOR: one template ══ --}}
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-6 space-y-5">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $entry['label'] }} email</h3>
                @if ($customized[$tpl])
                    <button wire:click="resetToDefault" data-confirm="Remove your customisation of the {{ $entry['label'] }} email and go back to the default?"
                            class="text-[11px] font-semibold text-rose-500 hover:text-rose-600">Remove customisation</button>
                @endif
            </div>

            {{-- Subject --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Subject</label>
                <input wire:model.live.debounce.300ms="subject" type="text" class="bkf-input">
                @error('subject')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Layout / sections — reorder, toggle, and edit each block --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Layout</label>
                    <button wire:click="resetTemplate" type="button" class="text-[11px] font-semibold text-gray-400 hover:underline">Reset layout</button>
                </div>

                <x-email.section-list :sections="$sections" :labels="$labels" :editableKeys="$editableKeys"
                                      :placeholders="$placeholders"
                                      prefix="sections" up="moveSectionUp" down="moveSectionDown" />
            </div>

            <button wire:click="save"
                    class="fx px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                Save {{ strtolower($entry['label']) }} email
            </button>
        </div>
    @endif
</div>

    {{-- ── RIGHT rail: the live preview ── --}}
    <x-slot:quick>
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">{{ $tpl ? 'Live preview' : 'Preview — submission receipt' }}</h3>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">{{ $tpl ? 'updates as you edit' : 'sample data' }}</span>
            </div>
            @if ($tpl)
                <x-email.preview :preview="$this->preview" :logo="$logo" :site="$site" />
            @else
                <p class="text-[12px] text-gray-500 dark:text-gray-400 leading-relaxed">Pick a template on the left to edit it — the live preview appears here, filled with sample data.</p>
            @endif
        </div>

        {{-- Related elsewhere in the app --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Related</h3>
            @foreach ([
                ['Forms', 'each form can customise its own receipt', $site->name.'/forms'],
                ['Assets', 'logos & images used in emails', $site->name.'/media'],
                ['Bookings', 'confirmations use these templates', $site->name.'/bookings'],
                ['Estimator', 'per-estimator quote drafts override the template', $site->name.'/estimates'],
            ] as [$rl, $rd, $ru])
                <a href="{{ url($ru) }}" class="fx flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[10px] text-gray-400 truncate">{{ $rd }}</span>
                    </span>
                    <svg class="w-3.5 h-3.5 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>
