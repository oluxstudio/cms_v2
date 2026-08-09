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

            {{-- Body --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Body</label>
                <textarea wire:model.live.debounce.300ms="body" rows="8"
                          class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 resize-none leading-relaxed"></textarea>
                @error('body')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                <p class="text-[11px] text-gray-400 mt-2 leading-relaxed">
                    Placeholders:
                    @foreach(['{name}', '{site}', '{type}', '{field:email}', '{fields}'] as $ph)
                        <code class="mx-0.5 px-1.5 py-0.5 rounded bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">{{ $ph }}</code>
                    @endforeach
                    <br>
                    <b>{field:key}</b> inserts one submitted value (e.g. <code class="px-1 rounded bg-gray-100 dark:bg-white/[0.06]">{field:phone}</code>);
                    <b>{fields}</b> lists everything they submitted. A summary is also appended automatically.
                </p>
            </div>

            <button wire:click="save"
                    class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                Save receipt email
            </button>
        </div>

        {{-- ── Live preview ── --}}
        <div>
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">Live preview</p>
            <div class="rounded-2xl border border-gray-200 dark:border-white/[0.08] overflow-hidden shadow-sm">
                <div class="px-4 py-2.5 bg-gray-50 dark:bg-white/[0.04] border-b border-gray-100 dark:border-white/[0.06]">
                    <p class="text-xs text-gray-400">Subject</p>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $this->preview['subject'] }}</p>
                </div>
                <div class="bg-[#f3f4f6] p-5">
                    <div class="max-w-md mx-auto bg-white rounded-2xl overflow-hidden shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            @if($logo)<img src="{{ $logo }}" alt="logo" class="h-8 object-contain">@else<span class="text-lg font-extrabold text-gray-900">{{ ucwords(str_replace('-', ' ', $site->name)) }}</span>@endif
                        </div>
                        <div class="px-6 py-5 text-[13px] leading-relaxed text-gray-700 whitespace-pre-line">{{ $this->preview['body'] }}</div>
                        <div class="px-6 pb-5">
                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">What you sent</p>
                                <p class="text-[12px] text-gray-600"><b>Name:</b> Alex · <b>Email:</b> alex@example.com</p>
                            </div>
                        </div>
                        <div class="px-6 py-3 border-t border-gray-100 text-[11px] text-gray-400">Sent by {{ ucwords(str_replace('-', ' ', $site->name)) }}.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
