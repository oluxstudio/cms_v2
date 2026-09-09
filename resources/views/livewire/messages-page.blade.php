<div class="main-body p-5 sm:p-6 h-full flex flex-col">
    {{-- Header: which account/site inbox this is --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">Messages</h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                <span class="inline-flex items-center gap-1.5 font-semibold text-indigo-600 dark:text-indigo-400">
                    🏠 {{ $this->site->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $this->site->name)) }}
                </span>
                · team inbox for this account
            </p>
        </div>
        @unless($this->canSend)
            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">Read-only — your role can't send messages</span>
        @endunless
    </div>

    {{-- Other inboxes: the same user in other accounts/teams --}}
    @if($this->otherInboxes !== [])
    <div class="flex flex-wrap items-center gap-1.5 mb-4">
        <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mr-1">Other inboxes</span>
        @foreach($this->otherInboxes as $inbox)
            <a href="{{ url($inbox['name'].'/messages') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                {{ $inbox['label'] }}
                @if($inbox['unread'] > 0)
                    <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-indigo-600 text-white text-[10px] font-bold flex items-center justify-center">{{ $inbox['unread'] }}</span>
                @endif
            </a>
        @endforeach
    </div>
    @endif

    <x-carousel :labels="['💬 Chats', '📨 Conversation']" class="gap-0 lg:gap-4">
        {{-- Conversations --}}
        <x-carousel.slide class="lg:!w-[280px] pb-20 lg:pb-0">
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-y-auto max-h-full">
            @foreach($this->conversations as $c)
            <button wire:click="openThread('{{ $c['key'] }}')" wire:key="conv-{{ $c['key'] }}"
                    class="w-full flex items-center gap-3 px-4 py-3 text-left border-b border-gray-50 dark:border-white/[0.04] last:border-0 transition-colors
                           {{ $thread === $c['key'] ? 'bg-indigo-50/60 dark:bg-indigo-500/[0.08]' : 'hover:bg-gray-50 dark:hover:bg-white/[0.02]' }}">
                <span class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold shrink-0
                             {{ $c['key'] === 'team' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300' }}">
                    {{ $c['key'] === 'team' ? '#' : strtoupper(mb_substr($c['name'], 0, 1)) }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <b class="text-sm text-gray-900 dark:text-white truncate">{{ $c['name'] }}</b>
                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400 shrink-0">{{ $c['role'] }}</span>
                    </span>
                    <span class="block text-xs text-gray-400 truncate">{{ $c['last'] ? \Illuminate\Support\Str::limit($c['last'], 42) : 'No messages yet' }}</span>
                </span>
                <span class="flex flex-col items-end gap-1 shrink-0">
                    @if($c['at'])<span class="text-[10px] text-gray-400">{{ $c['at']->shortRelativeDiffForHumans() }}</span>@endif
                    @if($c['unread'] > 0)
                        <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-indigo-600 text-white text-[10px] font-bold flex items-center justify-center">{{ $c['unread'] }}</span>
                    @endif
                </span>
            </button>
            @endforeach
        </div>

        </x-carousel.slide>

        {{-- Thread --}}
        <x-carousel.slide class="lg:flex-1 pb-20 lg:pb-0 flex flex-col min-h-0">
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm flex flex-col min-h-0 flex-1"
             wire:poll.5s>
            @php
                $current = collect($this->conversations)->firstWhere('key', $thread);
                $meId = auth()->id();
            @endphp
            <div class="px-5 py-3 border-b border-gray-100 dark:border-white/[0.05] flex items-center gap-2">
                <b class="text-sm text-gray-900 dark:text-white">{{ $thread === 'team' ? '# Team' : ($current['name'] ?? 'Conversation') }}</b>
                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400">{{ $current['role'] ?? '' }}</span>
                @if($thread === 'team')<span class="text-xs text-gray-400">— everyone on this site sees these</span>@endif
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3" id="msg-scroll"
                 x-data x-init="$el.scrollTop = $el.scrollHeight"
                 x-on:message-sent.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                @php $lastDay = null; @endphp
                @forelse($this->threadMessages as $m)
                    @if($m->created_at->toDateString() !== $lastDay)
                        @php $lastDay = $m->created_at->toDateString(); @endphp
                        <div class="text-center text-[10px] font-bold uppercase tracking-wide text-gray-300 dark:text-gray-600 py-1">{{ $m->created_at->isToday() ? 'Today' : $m->created_at->format('D j M') }}</div>
                    @endif
                    @php $mine = $m->sender_id === $meId; @endphp
                    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}" wire:key="msg-{{ $m->id }}">
                        <div class="max-w-[75%] group">
                            <p class="text-[10px] {{ $mine ? 'text-right' : '' }} text-gray-400 mb-0.5">
                                {{ $mine ? 'You' : $m->sender?->name }}
                                <span class="opacity-70">· {{ $this->roleLabels[$m->sender_id] ?? 'member' }}</span>
                                · {{ $m->created_at->format('g:i A') }}
                                @if($mine)
                                    <button wire:click="deleteMessage('{{ $m->id }}')" data-confirm="Delete this message?" class="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-600 transition-opacity ml-1">✕</button>
                                @endif
                            </p>
                            <div class="px-3.5 py-2.5 rounded-2xl text-sm whitespace-pre-line break-words
                                        {{ $mine ? 'bg-indigo-600 text-white rounded-br-md' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-800 dark:text-gray-100 rounded-bl-md' }}">{{ $m->body }}</div>
                        </div>
                    </div>
                @empty
                    <div class="h-full flex flex-col items-center justify-center text-center py-16">
                        <span class="text-4xl mb-2">💬</span>
                        <p class="text-sm text-gray-400">No messages yet — say hello!</p>
                    </div>
                @endforelse
            </div>

            @if($this->canSend)
            <form wire:submit="send" class="px-4 py-3 border-t border-gray-100 dark:border-white/[0.05] flex items-end gap-2">
                <textarea wire:model="body" rows="1" placeholder="Message {{ $thread === 'team' ? 'the team' : ($current['name'] ?? '') }}…"
                          x-data x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send() }"
                          class="flex-1 px-3.5 py-2.5 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] resize-none focus:outline-none focus:border-indigo-400"></textarea>
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors shrink-0">
                    <span wire:loading.remove wire:target="send">Send</span>
                    <span wire:loading wire:target="send">…</span>
                </button>
            </form>
            @error('body')<p class="px-5 pb-2 text-xs text-red-500">{{ $message }}</p>@enderror
            @endif
        </div>
        </x-carousel.slide>
    </x-carousel>
</div>
