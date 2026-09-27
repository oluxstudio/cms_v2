{{-- Per-form receipt email template editor + live preview. --}}
    {{-- ── Receipt email template (per form) ─────────────────────── --}}
    <div class="mt-6 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Receipt email</h3>
                <p class="mt-1 text-xs text-gray-400 max-w-xl">
                    The email the visitor gets after submitting this form. By default it uses your
                    <a href="{{ url($site->name.'/emails') }}" class="underline">site template</a> — turn on customising to give this form its own.
                </p>
            </div>
            <label class="relative inline-flex items-center gap-2 cursor-pointer shrink-0">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Customise for this form</span>
                <input type="checkbox" wire:model.live="fbTemplate.customized" class="sr-only">
                <span class="bkf-switch"></span>
            </label>
        </div>

        @if ($fbTemplate['customized'] ?? false)
            <div class="mt-5 grid lg:grid-cols-2 gap-6">
                {{-- Editor --}}
                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Subject</label>
                        <input wire:model.live.debounce.300ms="fbTemplate.subject" type="text"
                               class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Layout</label>
                            <button wire:click="resetTemplateToSiteDefault" type="button" class="text-[11px] font-semibold text-gray-400 hover:text-indigo-500">Reset to site default</button>
                        </div>
                        <x-email.section-list :sections="$fbTemplate['sections']" :labels="$tplLabels" :editableKeys="$editableKeys"
                                              prefix="fbTemplate.sections" up="moveTplSectionUp" down="moveTplSectionDown" />
                    </div>
                </div>
                {{-- Preview --}}
                <div>
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">Live preview</p>
                    <x-email.preview :preview="$this->templatePreview" :logo="$siteLogo" :site="$site" />
                </div>
            </div>
        @endif
    </div>

