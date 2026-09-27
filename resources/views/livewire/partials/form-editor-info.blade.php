{{-- Form metadata editor (title/slug/description/active) + Save/Cancel. Uses fb* state. --}}
            <div x-data="{ slugEdited: {{ $activeFormId ? 'true' : 'false' }} }"
                 class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5 space-y-4">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    Form Info
                </h3>

                {{-- Title --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <x-field.text wire:model.blur="fbTitle" placeholder="e.g. Contact Us"
                           x-on:input="
                               if (!slugEdited) {
                                   const slug = $event.target.value
                                       .toLowerCase()
                                       .trim()
                                       .replace(/[^a-z0-9\s-]/g, '')
                                       .replace(/\s+/g, '-')
                                       .replace(/-+/g, '-')
                                       .replace(/^-+|-+$/g, '');
                                   $wire.set('fbName', slug);
                               }
                           " />
                    @error('fbTitle')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">
                        Slug <span class="text-red-500">*</span>
                    </label>
                    <x-field.text wire:model.blur="fbName" placeholder="contact-us" mono
                           x-on:input="slugEdited = true" />
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        API endpoint: <span class="font-mono">…/form/{{ $fbName ?: '{slug}' }}</span>
                    </p>
                    @error('fbName')
                        <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">
                        Description
                    </label>
                    <x-field.textarea model="fbDescription" rows="3" placeholder="What is this form for?" class="resize-none" />
                    @error('fbDescription')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Active toggle --}}
                <div class="flex items-center justify-between pt-1">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Accept submissions</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                            Disable to pause incoming responses.
                        </p>
                    </div>
                    <x-field.toggle model="fbIsActive" live />
                </div>
            </div>

            {{-- Save / Cancel --}}
            <div class="flex gap-2">
                <button wire:click="saveForm"
                        class="flex-1 flex items-center justify-center gap-2
                               bg-indigo-600 hover:bg-indigo-700 text-white
                               text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Form
                </button>
                <button wire:click="backToList"
                        class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-white/10
                               bg-white dark:bg-[#1d1e2a] text-sm font-semibold text-gray-600 dark:text-gray-300
                               hover:bg-gray-50 dark:hover:bg-white/[0.05] transition-colors">
                    Cancel
                </button>
            </div>

