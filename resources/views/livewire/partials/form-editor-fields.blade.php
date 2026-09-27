{{-- Field builder column (includes its xl:col-span-2 wrapper). Uses fbFields. --}}
        {{-- ── Right col: field builder ─────────────────────────── --}}
        <div class="xl:col-span-2 space-y-3">

            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    Fields <span class="font-normal normal-case text-gray-400">({{ count($fbFields) }})</span>
                </h3>
                <button wire:click="addField"
                        class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5
                               rounded-xl border border-indigo-200 dark:border-indigo-500/40
                               bg-white dark:bg-[#1d1e2a] text-indigo-600 dark:text-indigo-400
                               hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Field
                </button>
            </div>

            @if (empty($fbFields))
                <div class="flex flex-col items-center gap-3 py-12
                            rounded-2xl border border-dashed border-gray-200 dark:border-white/10
                            bg-white dark:bg-[#1d1e2a]">
                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 6h16M4 10h16M4 14h10"/>
                    </svg>
                    <p class="text-sm text-gray-400 dark:text-gray-500">No fields yet. Click <strong>Add Field</strong> to start.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($fbFields as $index => $field)
                        @php
                            $needsOptions = in_array($field['type'] ?? 'text', ['select', 'radio']);
                            $needsRange   = in_array($field['type'] ?? 'text', ['text','email','tel','number','url','date','textarea']);
                        @endphp

                        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] p-4 space-y-3">

                            {{-- Field header --}}
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-indigo-50 dark:bg-indigo-500/10
                                             flex items-center justify-center
                                             text-xs font-bold text-indigo-500 shrink-0">
                                    {{ $index + 1 }}
                                </span>
                                <span class="flex-1 text-xs font-semibold text-gray-600 dark:text-gray-400 truncate">
                                    {{ $field['label'] ?: 'Untitled Field' }}
                                    @if ($field['key']) <span class="font-mono font-normal text-gray-400">({{ $field['key'] }})</span> @endif
                                </span>
                                {{-- Move up/down --}}
                                <button wire:click="moveFieldUp({{ $index }})"
                                        @if ($index === 0) disabled @endif
                                        class="p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
                                               disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                    </svg>
                                </button>
                                <button wire:click="moveFieldDown({{ $index }})"
                                        @if ($index === count($fbFields) - 1) disabled @endif
                                        class="p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
                                               disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <button wire:click="removeField({{ $index }})" data-confirm="Remove this field?"
                                        class="p-1 rounded text-red-400 hover:text-red-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Row 1: the essentials — label, type, required --}}
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                                        Label <span class="text-red-500">*</span>
                                    </label>
                                    <x-field.text wire:model.blur="fbFields.{{ $index }}.label" placeholder="First Name" />
                                    @error("fbFields.{$index}.label")
                                        <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Type</label>
                                    <select wire:model.live="fbFields.{{ $index }}.type"
                                            class="bkf-input">
                                        <optgroup label="Text">
                                            <option value="text">Text</option>
                                            <option value="email">Email</option>
                                            <option value="tel">Phone</option>
                                            <option value="url">URL</option>
                                            <option value="textarea">Textarea</option>
                                        </optgroup>
                                        <optgroup label="Numeric / Date">
                                            <option value="number">Number</option>
                                            <option value="date">Date</option>
                                        </optgroup>
                                        <optgroup label="Choice">
                                            <option value="select">Dropdown (select)</option>
                                            <option value="radio">Radio</option>
                                            <option value="checkbox">Checkbox (boolean)</option>
                                        </optgroup>
                                    </select>
                                </div>
                                {{-- Required toggle --}}
                                <div class="flex items-end pb-0.5">
                                    <x-field.check model="fbFields.{{ $index }}.required" text="Required" />
                                </div>
                            </div>

                            {{-- Options (select / radio) stay visible — they define the field. --}}
                            @if ($needsOptions)
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                                        Options <span class="font-normal">(comma-separated)</span>
                                    </label>
                                    <x-field.text model="fbFields.{{ $index }}.options" placeholder="Option A, Option B, Option C" />
                                </div>
                            @endif

                            <x-panel-group label="Field options" hint="key, placeholder, limits">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                    <div>
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                                            Key <span class="text-red-500">*</span>
                                        </label>
                                        <x-field.text wire:model.blur="fbFields.{{ $index }}.key" placeholder="first_name" mono />
                                        @error("fbFields.{$index}.key")
                                            <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    {{-- Placeholder (not for checkbox) --}}
                                    @if (($field['type'] ?? 'text') !== 'checkbox')
                                        <div>
                                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Placeholder</label>
                                            <x-field.text model="fbFields.{{ $index }}.placeholder" placeholder="Optional hint…" />
                                        </div>
                                    @endif
                                    {{-- Min / Max (text types) --}}
                                    @if ($needsRange)
                                        <div class="flex gap-2">
                                            <div class="flex-1">
                                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Min</label>
                                                <x-field.text model="fbFields.{{ $index }}.min" placeholder="—" />
                                            </div>
                                            <div class="flex-1">
                                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Max</label>
                                                <x-field.text model="fbFields.{{ $index }}.max" placeholder="—" />
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </x-panel-group>

                        </div>
                    @endforeach
                </div>

                {{-- Add field (bottom shortcut) --}}
                <button wire:click="addField"
                        class="w-full py-3 rounded-2xl border border-dashed border-gray-200 dark:border-white/10
                               bg-white dark:bg-[#1d1e2a] text-xs font-semibold text-gray-400 dark:text-gray-500
                               hover:border-indigo-300 dark:hover:border-indigo-500/40
                               hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors">
                    + Add another field
                </button>
            @endif

        </div>
