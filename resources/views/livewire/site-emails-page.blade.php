<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">

    <x-page-heading segment="emails" title="Emails"
        subtitle="The branded receipt every visitor gets when they submit a form, make a booking or get in touch." />

    @if ($successMessage)
        <p class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-sm text-emerald-700 dark:text-emerald-400">{{ $successMessage }}</p>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">

        {{-- ── Editor ── --}}
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
                <input wire:model.live.debounce.300ms="subject" type="text"
                       class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
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
                    class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                Save receipt email
            </button>
        </div>

        {{-- ── Live preview ── --}}
        <div>
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">Live preview</p>
            <x-email.preview :preview="$this->preview" :logo="$logo" :site="$site" />
        </div>
    </div>
</div>
