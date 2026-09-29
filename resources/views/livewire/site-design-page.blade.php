@php
    $currentTpl = $applied?->template;
    $currentName = $currentTpl?->name ?? $applied?->name ?? ($curatedFallback['name'] ?? null);
    $currentThumb = $currentTpl?->thumbnail_url ?? ($curatedFallback['thumbnail'] ?? null);
    $currentBy = $currentTpl?->source === 'upload' ? 'you (uploaded)' : ($currentTpl?->creator?->name ?? 'Olux Studio');
@endphp
<x-tri-layout title="Design" :subtitle="'How '.$site->name.' looks — its template, changed safely with a restore point.'" :site-name="$site->name"
    :labels="['📊 Overview', '🎨 Design', 'ℹ️ Summary']">

    <x-slot:header>
        <div class="flex items-center gap-1 p-1 rounded-full bg-gray-100 dark:bg-white/[0.05]">
            <span class="px-4 py-1.5 rounded-full text-sm font-semibold bg-white dark:bg-[#1d1e2a] text-gray-900 dark:text-white shadow-sm">Design</span>
            <a href="{{ url($site->name.'/addons') }}" class="px-4 py-1.5 rounded-full text-sm font-semibold text-gray-500 dark:text-gray-400">Add-ons</a>
            <a href="{{ url($site->name.'/publish') }}" class="px-4 py-1.5 rounded-full text-sm font-semibold text-gray-500 dark:text-gray-400">Domain</a>
        </div>
    </x-slot:header>

    {{-- ══ LEFT rail: page stats ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$currentName ?? 'None'" label="Current template"
                icon="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM4 10h16"
                :sub="$currentName ? 'by '.$currentBy : 'pick one to begin'" />
        <x-tile accent="lime" :value="$libraryTemplates->count()" label="In your library"
                icon="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5z"
                sub="usable on this site" />
        <x-tile accent="lavender" :value="$applied?->templateVersion?->version ?? ($applied ? 'current' : '—')" label="Version"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                :sub="$applied?->applied_at ? 'applied '.$applied->applied_at->diffForHumans() : 'nothing applied'" />
        <x-tile accent="sky" :value="$site->pages()->count()" label="Pages"
                icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 8.414V19a2 2 0 01-2 2z"
                sub="kept when you switch" />
    </div>
    </x-slot:rail>

{{-- ══ CENTER ══ --}}
<div class="max-w-[52rem] mx-auto">

    {{-- current template card / empty state --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-5">
        @if ($currentName)
            <div class="flex flex-wrap items-center gap-4">
                <div class="w-32 h-24 rounded-xl overflow-hidden bg-gray-100 dark:bg-white/[0.05] shrink-0">
                    @if ($currentThumb)<img src="{{ $currentThumb }}" class="w-full h-full object-cover object-top" alt="">@endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Current template</p>
                    <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $currentName }}</h2>
                    <p class="text-[12.5px] text-gray-500 dark:text-gray-400">
                        by {{ $currentBy }}
                        · {{ $applied?->templateVersion?->version ?? 'current version' }}
                        @if ($applied?->applied_at) · applied {{ $applied->applied_at->diffForHumans() }} @endif
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if ($canUndo)
                        <button wire:click="undo" class="fx min-h-[44px] px-4 rounded-xl text-[13px] font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.05] text-gray-700 dark:text-gray-200">Undo</button>
                    @endif
                    <button wire:click="openPicker" class="fx min-h-[48px] px-5 rounded-xl text-[14px] font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">Change template</button>
                </div>
            </div>
        @else
            <div class="text-center py-8">
                <p class="text-sm font-extrabold text-gray-900 dark:text-white">No template applied yet</p>
                <p class="text-[13px] text-gray-500 dark:text-gray-400 mt-1">Pick one from your library — your pages and content stay yours.</p>
                <button wire:click="openPicker" class="fx mt-4 min-h-[48px] px-6 rounded-xl text-[14px] font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">Choose a template</button>
            </div>
        @endif
    </div>

    {{-- Upload your own design: a zipped Nuxt app becomes a private library template --}}
    @php $uploading = $uploads->contains(fn ($u) => $u->inProgress()); @endphp
    <div class="mt-4 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-5"
         @if ($uploading) wire:poll.5s @endif>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-extrabold text-gray-900 dark:text-white">Upload your own design</h2>
                <p class="text-[13px] text-gray-500 dark:text-gray-400 mt-1">
                    Upload a zipped Nuxt app. We connect its pages and text to the editor, build it, and add it to your library.
                    It stays private to your account.
                </p>
            </div>
        </div>

        <form wire:submit="uploadApp" class="mt-4 grid gap-3 sm:grid-cols-[1fr_14rem_auto] sm:items-end">
            <label class="block min-w-0">
                <span class="bkf-label">Nuxt app (.zip, up to 60 MB)</span>
                <input type="file" wire:model="appZip" accept=".zip,application/zip"
                       class="bkf-input w-full file:mr-3 file:rounded-lg file:border-0 file:px-3 file:py-1.5 file:text-[12.5px] file:font-bold file:bg-gray-100 dark:file:bg-white/[0.08] file:text-gray-700 dark:file:text-gray-200">
            </label>
            <label class="block">
                <span class="bkf-label">Name (optional)</span>
                <input type="text" wire:model="uploadName" maxlength="60" placeholder="e.g. Spring refresh" class="bkf-input w-full">
            </label>
            <button type="submit" wire:loading.attr="disabled" wire:target="appZip,uploadApp"
                    class="fx min-h-[44px] px-5 rounded-xl text-[14px] font-bold shadow-sm disabled:opacity-50" style="background:var(--primary);color:var(--on-primary)">
                <span wire:loading.remove wire:target="appZip,uploadApp">Upload</span>
                <span wire:loading wire:target="appZip">Sending…</span>
                <span wire:loading wire:target="uploadApp">Starting…</span>
            </button>
        </form>
        @error('appZip')<p class="mt-2 text-[12.5px] font-semibold text-rose-600">{{ $message }}</p>@enderror
        @error('uploadName')<p class="mt-2 text-[12.5px] font-semibold text-rose-600">{{ $message }}</p>@enderror

        <details class="mt-3 text-[12.5px] text-gray-600 dark:text-gray-300">
            <summary class="cursor-pointer font-bold text-gray-700 dark:text-gray-200">What the zip should contain</summary>
            <ul class="mt-2 ml-4 list-disc space-y-1">
                <li>A Nuxt 3 or 4 app: <code>package.json</code>, <code>nuxt.config.ts</code> and a <code>pages</code> folder (or <code>app/pages</code>).</li>
                <li>Page sections as components, so each becomes an editable block.</li>
                <li>Images and fonts in <code>public</code>. Leave out <code>node_modules</code>, <code>.nuxt</code> and <code>.output</code>.</li>
            </ul>
        </details>

        @if ($uploads->isNotEmpty())
        <div class="mt-4 border-t border-gray-100 dark:border-white/[0.06] pt-3 space-y-2" aria-live="polite">
            @foreach ($uploads as $u)
                @php
                    [$pillBg, $pillFg, $pillText] = match ($u->status) {
                        'ready' => ['#dcfce7', '#15803d', 'Ready'],
                        'failed' => ['#ffe4e6', '#be123c', 'Failed'],
                        default => ['#e0f2fe', '#0369a1', 'In progress'],
                    };
                @endphp
                <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] px-3.5 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[13.5px] font-bold text-gray-900 dark:text-white truncate max-w-[16rem]">{{ $u->name ?: $u->original_filename ?: 'Upload' }}</span>
                        <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pillBg }};color:{{ $pillFg }}">{{ $pillText }}</span>
                        <span class="text-[11px] text-gray-400">{{ $u->created_at->diffForHumans() }}</span>
                        <span class="ml-auto flex items-center gap-2">
                            @if ($u->status === 'ready' && $u->template)
                                @if ($preview = $u->template->previewUrl($site->name))
                                    <x-preview-button :href="$preview" label="Preview" small />
                                @endif
                                <button wire:click="applyUpload('{{ $u->id }}')" wire:loading.attr="disabled"
                                        class="fx min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold" style="background:var(--primary);color:var(--on-primary)">Use on this site</button>
                            @elseif ($u->status === 'failed')
                                <button wire:click="dismissUpload('{{ $u->id }}')" class="fx min-h-[36px] px-3 rounded-xl text-[12.5px] font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-600 dark:text-gray-300">Dismiss</button>
                            @endif
                        </span>
                    </div>
                    @if ($u->inProgress())
                        <p class="mt-1.5 flex items-center gap-2 text-[12.5px] text-gray-600 dark:text-gray-300">
                            <svg class="animate-spin w-3.5 h-3.5" style="color:var(--primary)" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            {{ $u->step ?: 'Working' }}…
                        </p>
                    @elseif ($u->status === 'failed')
                        <p class="mt-1.5 text-[12.5px] text-rose-600 dark:text-rose-400">{{ $u->error }}</p>
                    @elseif ($u->warnings)
                        <p class="mt-1.5 text-[12px] text-gray-500 dark:text-gray-400">
                            Added to your library{{ $u->lint_score !== null ? ' · quality '.$u->lint_score.'/100' : '' }}.
                            Worth a look: {{ collect($u->warnings)->take(2)->implode(' · ') }}
                        </p>
                    @else
                        <p class="mt-1.5 text-[12px] text-gray-500 dark:text-gray-400">Added to your library.</p>
                    @endif
                </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

    {{-- ══ RIGHT rail: summary + related ══ --}}
    <x-slot:quick>
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Your library</h3>
            @forelse ($libraryTemplates->take(5) as $lt)
                <div class="flex items-center gap-2.5 py-1.5 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="w-9 h-7 rounded-md overflow-hidden bg-gray-100 dark:bg-white/[0.05] shrink-0">
                        @if ($lt->thumbnail_url)<img src="{{ $lt->thumbnail_url }}" class="w-full h-full object-cover object-top" alt="">@endif
                    </span>
                    <span class="min-w-0 flex-1 text-[12.5px] font-bold text-gray-800 dark:text-gray-100 truncate">{{ $lt->name }}</span>
                    @if ($currentTpl && $currentTpl->id === $lt->id)<span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-600">in use</span>@endif
                </div>
            @empty
                <p class="text-[11px] text-gray-400 py-2">Nothing in your library yet.</p>
            @endforelse
            <a href="{{ route('marketplace', $site->name) }}" class="fx block mt-2 text-[12px] font-bold hover:underline" style="color:var(--primary)">Browse templates →</a>
        </div>

        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Related</h3>
            @foreach ([
                ['Edit the site', 'change content inside this design', $site->name.'/connect'],
                ['Add-ons', 'features like bookings & the store', $site->name.'/addons'],
                ['Go live', 'domain & serving status', $site->name.'/publish'],
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

    {{-- ══ Change-template modal ══ --}}
    @if ($picking)
    <div class="fixed inset-0 z-50 grid place-items-center p-4" x-data x-on:keydown.escape.window="$wire.closePicker()">
        <div class="absolute inset-0 bg-black/40" wire:click="closePicker"></div>
        <div class="relative bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-y-auto p-6">
            <div class="flex items-start justify-between gap-3 mb-4">
                <h2 class="text-base font-extrabold text-gray-900 dark:text-white">Choose a template for {{ $site->name }}</h2>
                <button wire:click="closePicker" aria-label="Close" class="fx shrink-0 w-9 h-9 rounded-full grid place-items-center text-white shadow-md" style="background:var(--primary)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach ($libraryTemplates as $lt)
                    <button wire:click="select('{{ $lt->id }}')"
                            class="fx text-left rounded-xl overflow-hidden border-2 {{ $selectedId === $lt->id ? '' : 'border-gray-100 dark:border-white/[0.08]' }}"
                            @if ($selectedId === $lt->id) style="border-color:var(--primary)" @endif>
                        <span class="block aspect-[4/3] bg-gray-100 dark:bg-white/[0.05]">
                            @if ($lt->thumbnail_url)<img src="{{ $lt->thumbnail_url }}" class="w-full h-full object-cover object-top" alt="">@endif
                        </span>
                        <span class="block p-2.5">
                            <span class="block text-[12.5px] font-extrabold text-gray-900 dark:text-white truncate">{{ $lt->name }}</span>
                            @if ($currentTpl && $currentTpl->id === $lt->id)
                                <span class="text-[10px] font-bold text-emerald-600">Current template</span>
                            @else
                                <span class="text-[10px] text-gray-400">{{ $lt->category ?: 'Template' }}</span>
                            @endif
                        </span>
                    </button>
                @endforeach
                <a href="{{ route('marketplace', $site->name) }}" class="fx rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.12] grid place-items-center min-h-[120px] text-[12.5px] font-bold text-gray-500 dark:text-gray-300 text-center p-3 hover:border-gray-400">
                    + Browse more
                </a>
            </div>

            <div class="mt-4 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200/70 dark:border-amber-500/20 text-[12.5px] text-amber-800 dark:text-amber-300">
                This changes how <span class="font-bold">{{ $site->name }}</span> looks. Your pages, text and bookings stay.
                We save a restore point first, so you can switch back any time.
                @if ($selected && $wouldEnable->isNotEmpty())
                    <span class="block mt-1 font-bold">{{ $selected->name }} also turns on: {{ $wouldEnable->implode(', ') }}.</span>
                @endif
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 mt-4">
                @php
                    // The template's shell with ?site= renders THIS site's content in it.
                    $selPreview = $selected ? ($selected->live_preview_url ?: $selected->previewUrl($site->name)) : null;
                @endphp
                <a @if ($selPreview) href="{{ $selPreview }}" target="_blank" rel="noopener" @endif
                   class="fx min-h-[44px] px-4 leading-[44px] rounded-xl text-[13px] font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.05] text-gray-700 dark:text-gray-200 {{ $selPreview ? '' : 'opacity-40 pointer-events-none' }}">
                    Preview with my content
                </a>
                <button wire:click="apply" @disabled(! $selectedId) wire:loading.attr="disabled"
                        class="fx min-h-[48px] px-5 rounded-xl text-[14px] font-bold shadow-sm disabled:opacity-40" style="background:var(--primary);color:var(--on-primary)">
                    <span wire:loading.remove wire:target="apply">Apply template</span>
                    <span wire:loading wire:target="apply">Applying…</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</x-tri-layout>
