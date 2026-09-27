{{-- Response cards for the active form: empty state, expandable rows, pagination.
     Expects: $responses, $openId, $site (included from site-forms-page). --}}
    {{-- Empty --}}
    @if ($responses->isEmpty())
        <div class="flex flex-col items-center gap-3 py-20 rounded-2xl
                    border border-dashed border-gray-200 dark:border-white/10
                    bg-white dark:bg-[#1d1e2a]">
            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
            <p class="text-sm text-gray-400 dark:text-gray-500 font-medium">No responses yet</p>
        </div>

    @else
        <div class="space-y-2">
            @foreach ($responses as $response)
                @php
                    $isOpen  = $openId === $response->id;
                    $fields  = $response->fields ?? [];
                    $preview = collect($fields)->filter(fn($v) => is_string($v) && $v !== '')->first() ?? '—';
                @endphp

                <div class="rounded-2xl border overflow-hidden transition-all
                            {{ $isOpen ? 'border-indigo-200 dark:border-indigo-500/30 shadow-sm' : 'border-gray-100 dark:border-white/[0.06]' }}
                            bg-white dark:bg-[#1d1e2a]">

                    <button wire:click="toggleOpen('{{ $response->id }}')"
                            class="w-full flex items-center gap-3 px-4 py-3.5 text-left
                                   hover:bg-gray-50 dark:hover:bg-white/[0.03] transition-colors">

                        <span class="w-2 h-2 rounded-full shrink-0
                                     {{ $response->read_at ? 'bg-transparent ring-1 ring-gray-300 dark:ring-white/20' : 'bg-indigo-500' }}">
                        </span>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">
                                {{ Str::limit((string) $preview, 90) }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ $response->created_at->format('M j, Y · g:i A') }}
                                @if ($response->ip_address)
                                    · <span class="font-mono">{{ $response->ip_address }}</span>
                                @endif
                            </p>
                        </div>

                        <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform {{ $isOpen ? 'rotate-180' : '' }}"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    @if ($isOpen)
                        <div class="border-t border-gray-100 dark:border-white/[0.05] px-4 pb-4 pt-4 space-y-4">

                            {{-- Fields grid --}}
                            @if (! empty($fields))
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach ($fields as $key => $value)
                                        <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] px-4 py-3">
                                            <p class="text-xs font-semibold uppercase tracking-wider
                                                       text-gray-400 dark:text-gray-500 mb-1">
                                                {{ str_replace(['-', '_'], ' ', $key) }}
                                            </p>
                                            <p class="text-sm text-gray-800 dark:text-gray-200 break-words">
                                                @if (is_array($value))
                                                    {{ implode(', ', $value) }}
                                                @elseif (is_bool($value))
                                                    {{ $value ? 'Yes' : 'No' }}
                                                @else
                                                    {{ $value ?: '—' }}
                                                @endif
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Meta + delete --}}
                            <div class="flex items-center justify-between flex-wrap gap-3 pt-3
                                        border-t border-gray-100 dark:border-white/[0.05]">
                                <div class="flex items-center gap-4 text-xs text-gray-400 dark:text-gray-500 flex-wrap">
                                    <span>{{ $response->created_at->format('M j, Y \a\t g:i A') }}</span>
                                    @if ($response->ip_address)
                                        <span class="font-mono">IP: {{ $response->ip_address }}</span>
                                    @endif
                                    @if ($response->read_at)
                                        <span class="text-emerald-600 dark:text-emerald-400">
                                            ✓ Read {{ $response->read_at->diffForHumans() }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    {{-- Convert to Contact / Converted indicator --}}
                                    @if ($response->contact)
                                        <a href="{{ url($site->name.'/contacts').'?contact='.$response->contact->id }}"
                                           class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl
                                                  text-emerald-600 dark:text-emerald-400
                                                  border border-emerald-200 dark:border-emerald-500/30
                                                  hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            View Contact
                                        </a>
                                    @else
                                        <button wire:click="convertToContact('{{ $response->id }}')"
                                                class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl
                                                       text-indigo-600 dark:text-indigo-400
                                                       border border-indigo-200 dark:border-indigo-500/30
                                                       hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                            </svg>
                                            Convert to Contact
                                        </button>
                                    @endif

                                    <button wire:click="deleteResponse('{{ $response->id }}')" data-confirm="Delete this response?"
                                            data-confirm="Delete this response permanently?"
                                            class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl
                                                   text-red-600 dark:text-red-400
                                                   border border-red-200 dark:border-red-500/30
                                                   hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            @endforeach
        </div>

        @if ($responses->hasPages())
            <div class="pt-2">{{ $responses->links() }}</div>
        @endif
    @endif

