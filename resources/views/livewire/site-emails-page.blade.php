<x-tri-layout title="Emails" subtitle="The branded receipt every visitor gets when they submit a form, make a booking or get in touch." :site-name="$site->name"
    :labels="['📊 Overview', '✉️ Emails', '👁 Preview']" quick-width="lg:!w-[410px]">

    {{-- ── LEFT rail: page stats as tiles ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$logo ? 'Set' : 'Missing'" label="Logo"
                icon="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                :sub="$logo ? 'branding in place' : 'add one below'" />
        <x-tile accent="lime" :value="$accent ?? '—'" label="Accent colour"
                icon="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485"
                sub="buttons + highlights" />
        <x-tile accent="lavender" :value="count(array_filter($sections, fn ($s) => $s['enabled'] ?? false))" label="Sections on"
                icon="M4 6h16M4 10h16M4 14h16M4 18h16"
                :sub="count($sections).' in the layout'" />
        <x-tile accent="sky" :value="\Illuminate\Support\Str::limit($subject, 12) ?: '—'" label="Subject"
                icon="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                sub="what recipients see first" />
    </div>
    </x-slot:rail>

{{-- ── CENTER: the editor ── --}}
<div class="max-w-[52rem] mx-auto">

    @if ($successMessage)
        <p class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-sm text-emerald-700 dark:text-emerald-400">{{ $successMessage }}</p>
    @endif

    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-6 space-y-5">
        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Receipt email</h3>

        {{-- Logo — pick from Assets, paste a URL, or upload a new one --}}
        <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Logo</label>
            <div class="flex items-start gap-3">
                <div class="w-16 h-16 rounded-xl border border-gray-200 dark:border-white/[0.08] grid place-items-center overflow-hidden bg-gray-50 dark:bg-white/[0.04] shrink-0">
                    @if($logo)<img src="{{ $logo }}" alt="logo" class="max-w-full max-h-full object-contain">@else<span class="text-xs text-gray-400">None</span>@endif
                </div>
                <div class="flex-1 min-w-0 space-y-2">
                    {{-- Reusable asset picker: browse the site's asset library or paste a URL --}}
                    <x-asset-picker model="logo" :site="$site" type="image" placeholder="Logo URL, or pick from assets" />
                    <div class="flex items-center gap-3">
                        <label class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 cursor-pointer">
                            <span wire:loading.remove wire:target="logoUpload">⬆ Upload a new image</span>
                            <span wire:loading wire:target="logoUpload">Uploading…</span>
                            <input type="file" wire:model="logoUpload" accept="image/*" class="hidden">
                        </label>
                        @if($logo)<button wire:click="removeLogo" class="text-xs font-semibold text-rose-500 hover:text-rose-600">Remove</button>@endif
                    </div>
                    <p class="text-[11px] text-gray-400">Uploads are saved to your <a href="{{ url($site->name.'/media') }}" class="underline">Assets</a> so you can reuse them.</p>
                </div>
            </div>
            @error('logoUpload')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
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
                <button wire:click="resetTemplate" type="button" class="text-[11px] font-semibold text-gray-400 hover:text-indigo-500">Reset to default</button>
            </div>

            <x-email.section-list :sections="$sections" :labels="$labels" :editableKeys="$editableKeys"
                                  prefix="sections" up="moveSectionUp" down="moveSectionDown" />
        </div>

        <button wire:click="save"
                class="fx px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">
            Save receipt email
        </button>
    </div>
</div>

    {{-- ── RIGHT rail: the live preview (this page's summary IS the email) ── --}}
    <x-slot:quick>
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Live preview</h3>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">updates as you edit</span>
            </div>
            <x-email.preview :preview="$this->preview" :logo="$logo" :site="$site" />
        </div>

        {{-- Related elsewhere in the app --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Related</h3>
            @foreach ([
                ['Forms', 'each form can customise its own receipt', $site->name.'/forms'],
                ['Assets', 'logos & images used in emails', $site->name.'/media'],
                ['Bookings', 'confirmations use this branding too', $site->name.'/bookings'],
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
