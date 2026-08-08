<div class="main-body h-full flex flex-col">

    {{-- ── Page header ── --}}
    <div class="px-8 py-6 shrink-0 border-b border-gray-200 dark:border-white/[0.06]
                bg-white dark:bg-[#1d1e2a]">
        <p class="text-xs font-bold tracking-widest text-gray-400 dark:text-gray-500 uppercase mb-1">{{ $site->name }}</p>
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">Submissions</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400 text-sm">
            Form submissions, contact messages and newsletter subscribers from your site.
        </p>
    </div>

    {{-- ── Tab bar ── --}}
    <div class="flex items-center gap-1 px-8 pt-4 pb-0 shrink-0 border-b border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[#1d1e2a]">
        @foreach([
            ['key' => 'subscriptions', 'label' => 'Subscribers',    'count' => $counts['subscriptions'], 'badge' => 0],
            ['key' => 'contact',       'label' => 'Contact',         'count' => $counts['contact'],       'badge' => $counts['unread_contact']],
            ['key' => 'forms',         'label' => 'Custom Forms',    'count' => $counts['forms'],         'badge' => $counts['unread_forms']],
        ] as $t)
        <button wire:click="setTab('{{ $t['key'] }}')"
                class="relative flex items-center gap-2 pb-3 px-1 text-sm font-semibold border-b-2 transition-colors cursor-pointer
                       {{ $tab === $t['key']
                           ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                           : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
            {{ $t['label'] }}
            <span class="text-xs font-bold px-1.5 py-0.5 rounded-full
                         {{ $tab === $t['key']
                             ? 'bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400'
                             : 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400' }}">
                {{ $t['count'] }}
            </span>
            @if($t['badge'] > 0)
            <span class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white dark:ring-[#1d1e2a]"></span>
            @endif
        </button>
        @endforeach
    </div>

    {{-- ── Tab content ── --}}
    <div class="flex-1 overflow-y-auto bg-[#f0f2f7] dark:bg-[#16171d]">
    <div class="px-8 py-6">

    {{-- ════════════════════════════════════════════
         SUBSCRIPTIONS TAB
         ════════════════════════════════════════════ --}}
    @if($tab === 'subscriptions')

        @if($subscriptions->isEmpty())
        <x-empty-state icon="inbox" message="No subscribers yet. Add a newsletter sign-up form to your site." />
        @else
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-200 dark:border-white/[0.07] overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/[0.06]">
                        <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Name</th>
                        <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Email</th>
                        <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Status</th>
                        <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Source</th>
                        <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Date</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                    @foreach($subscriptions as $sub)
                    <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                        <td class="px-5 py-3.5 font-medium text-gray-800 dark:text-white">
                            {{ $sub->name ?: '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-gray-600 dark:text-gray-300 font-mono text-xs">
                            {{ $sub->email }}
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full
                                         {{ $sub->status === 'active'
                                             ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400'
                                             : 'bg-gray-100 dark:bg-white/[0.05] text-gray-500 dark:text-gray-400' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $sub->status === 'active' ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                {{ ucfirst($sub->status) }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-gray-400 dark:text-gray-500 truncate max-w-[140px]">
                            {{ $sub->source ?: '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">
                            {{ $sub->created_at->format('M j, Y') }}
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button wire:click="deleteSubmission('{{ $sub->id }}')" data-confirm="Delete this submission?"
                                    data-confirm="Delete this subscriber?"
                                    class="text-xs text-gray-400 hover:text-rose-500 transition-colors cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($subscriptions->hasPages())
            <div class="px-5 py-3 border-t border-gray-100 dark:border-white/[0.05]">
                {{ $subscriptions->links() }}
            </div>
            @endif
        </div>
        @endif

    @endif

    {{-- ════════════════════════════════════════════
         CONTACT TAB
         ════════════════════════════════════════════ --}}
    @if($tab === 'contact')

        @if($contacts->isNotEmpty())
        <div class="flex justify-end mb-3">
            <button wire:click="markAllRead"
                    class="flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400
                           hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Mark all read
            </button>
        </div>
        @endif

        @if($contacts->isEmpty())
        <x-empty-state icon="inbox" message="No contact messages yet. Add a contact form to your site." />
        @else
        <div class="space-y-2">
            @foreach($contacts as $msg)
            <div class="bg-white dark:bg-[#1d1e2a] rounded-xl border transition-colors
                        {{ $msg->isUnread() ? 'border-indigo-300 dark:border-indigo-500/40' : 'border-gray-200 dark:border-white/[0.07]' }}">

                {{-- Row header --}}
                <button wire:click="toggleOpen('{{ $msg->id }}')"
                        class="w-full flex items-center gap-4 px-5 py-4 text-left cursor-pointer">

                    {{-- Unread dot --}}
                    <span class="w-2 h-2 rounded-full shrink-0 {{ $msg->isUnread() ? 'bg-indigo-500' : 'bg-transparent' }}"></span>

                    {{-- Sender --}}
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500
                                flex items-center justify-center text-white text-xs font-black shrink-0">
                        {{ strtoupper(substr($msg->name, 0, 1)) }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-sm text-gray-900 dark:text-white">{{ $msg->name }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">{{ $msg->email }}</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                            @if($msg->subject) <span class="font-semibold">{{ $msg->subject }}:</span> @endif
                            {{ Str::limit($msg->message, 80) }}
                        </p>
                    </div>

                    <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0 whitespace-nowrap">
                        {{ $msg->created_at->diffForHumans() }}
                    </span>

                    <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform {{ $openId === $msg->id ? 'rotate-180' : '' }}"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Expanded body --}}
                @if($openId === $msg->id)
                <div class="px-5 pb-5 border-t border-gray-100 dark:border-white/[0.05] pt-4">
                    @if($msg->subject)
                    <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1">Subject</p>
                    <p class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ $msg->subject }}</p>
                    @endif
                    <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1">Message</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap">{{ $msg->message }}</p>
                    <div class="flex items-center gap-4 mt-4 pt-3 border-t border-gray-100 dark:border-white/[0.05]">
                        <a href="mailto:{{ $msg->email }}"
                           class="flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400
                                  hover:underline">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Reply to {{ $msg->email }}
                        </a>
                        <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">
                            {{ $msg->created_at->format('M j, Y · g:i a') }}
                        </span>
                        <button wire:click="deleteSubmission('{{ $msg->id }}')" data-confirm="Delete this submission?"
                                data-confirm="Delete this message?"
                                class="text-xs text-rose-500 hover:text-rose-600 transition-colors cursor-pointer font-semibold">
                            Delete
                        </button>
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @if($contacts->hasPages())
        <div class="mt-4">{{ $contacts->links() }}</div>
        @endif
        @endif

    @endif

    {{-- ════════════════════════════════════════════
         CUSTOM FORMS TAB
         ════════════════════════════════════════════ --}}
    @if($tab === 'forms')

        {{-- Form filter pills --}}
        @if($formList->isNotEmpty())
        <div class="flex items-center gap-2 mb-4 flex-wrap">
            <button wire:click="$set('formFilter', '')"
                    class="text-xs font-semibold px-3 py-1.5 rounded-full border transition-colors cursor-pointer
                           {{ $formFilter === '' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-200 dark:border-white/[0.08] text-gray-500 dark:text-gray-400 hover:border-indigo-400' }}">
                All
            </button>
            @foreach($formList as $f)
            <button wire:click="$set('formFilter', '{{ $f->id }}')"
                    class="text-xs font-semibold px-3 py-1.5 rounded-full border transition-colors cursor-pointer
                           {{ $formFilter == $f->id ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-200 dark:border-white/[0.08] text-gray-500 dark:text-gray-400 hover:border-indigo-400' }}">
                {{ $f->displayTitle() }}
            </button>
            @endforeach

            @if($counts['unread_forms'] > 0)
            <button wire:click="markAllRead"
                    class="ml-auto flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400
                           hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Mark all read
            </button>
            @endif
        </div>
        @endif

        @if($formResponses->isEmpty())
        <x-empty-state icon="inbox" message="No custom form submissions yet." />
        @else
        <div class="space-y-2">
            @foreach($formResponses as $sub)
            <div class="bg-white dark:bg-[#1d1e2a] rounded-xl border transition-colors
                        {{ $sub->isUnread() ? 'border-indigo-300 dark:border-indigo-500/40' : 'border-gray-200 dark:border-white/[0.07]' }}">

                <button wire:click="toggleOpen('{{ $sub->id }}')"
                        class="w-full flex items-center gap-4 px-5 py-4 text-left cursor-pointer">
                    <span class="w-2 h-2 rounded-full shrink-0 {{ $sub->isUnread() ? 'bg-indigo-500' : 'bg-transparent' }}"></span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400">
                                {{ $sub->form->displayTitle() }}
                            </span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                {{ count($sub->fields) }} {{ Str::plural('field', count($sub->fields)) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                            @foreach(array_slice($sub->fields, 0, 3) as $k => $v)
                                <span class="font-medium">{{ $k }}:</span> {{ Str::limit((string) $v, 20) }}@if(!$loop->last) · @endif
                            @endforeach
                        </p>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0 whitespace-nowrap">
                        {{ $sub->created_at->diffForHumans() }}
                    </span>
                    <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform {{ $openId === $sub->id ? 'rotate-180' : '' }}"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                @if($openId === $sub->id)
                <div class="px-5 pb-5 border-t border-gray-100 dark:border-white/[0.05] pt-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                        @foreach($sub->fields as $key => $value)
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-0.5">
                                {{ str_replace(['-', '_'], ' ', $key) }}
                            </p>
                            <p class="text-sm text-gray-800 dark:text-white whitespace-pre-wrap">
                                {{ is_array($value) ? implode(', ', $value) : $value }}
                            </p>
                        </div>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-3 mt-4 pt-3 border-t border-gray-100 dark:border-white/[0.05]">
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ $sub->created_at->format('M j, Y · g:i a') }}
                            @if($sub->ip_address) · {{ $sub->ip_address }} @endif
                        </span>
                        <button wire:click="deleteSubmission('{{ $sub->id }}')" data-confirm="Delete this submission?"
                                data-confirm="Delete this submission?"
                                class="ml-auto text-xs text-rose-500 hover:text-rose-600 transition-colors cursor-pointer font-semibold">
                            Delete
                        </button>
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @if($formResponses->hasPages())
        <div class="mt-4">{{ $formResponses->links() }}</div>
        @endif
        @endif

    @endif

    </div>
    </div>
</div>
