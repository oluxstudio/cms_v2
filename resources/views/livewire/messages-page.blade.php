@php
    $convs = $this->conversations;
    $unreadTotal = collect($convs)->sum('unread');
    $current = collect($convs)->firstWhere('key', $thread);
    $meId = auth()->id();
    $profile = $this->profile;
@endphp
<x-tri-layout title="Messages" :subtitle="($this->site->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $this->site->name))).' — team inbox for this account.'" :site-name="$this->site->name"
    :labels="['💬 Chats', '📨 Conversation', '👤 Profile']">

    <x-slot:header>
        @unless($this->canSend)
            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">Read-only — your role can't send messages</span>
        @endunless
    </x-slot:header>

    {{-- ══ LEFT rail: stats + the conversation list ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3 mb-4">
        <x-tile accent="ink" wide :value="$unreadTotal" label="Unread messages"
                icon="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                :sub="$unreadTotal ? 'waiting for you' : 'all caught up'" />
        <x-tile accent="lime" :value="count($convs)" label="Conversations"
                icon="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                sub="team + direct" />
        <x-tile accent="sky" :value="$this->team->count()" label="On the team"
                icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                sub="people you can message" />
    </div>

    {{-- Conversations --}}
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-y-auto max-h-[50vh]">
        @foreach($convs as $c)
        <button wire:click="openThread('{{ $c['key'] }}')" wire:key="conv-{{ $c['key'] }}"
                class="w-full flex items-center gap-3 px-4 py-3 text-left border-b border-gray-50 dark:border-white/[0.04] last:border-0 transition-colors
                       {{ $thread === $c['key'] ? 'bg-gray-50 dark:bg-white/[0.05]' : 'hover:bg-gray-50/60 dark:hover:bg-white/[0.02]' }}">
            <span class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold shrink-0
                         {{ $c['key'] === 'team' ? 'text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300' }}"
                  @if($c['key'] === 'team') style="background:var(--primary)" @endif>
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
                    <span class="min-w-[18px] h-[18px] px-1 rounded-full text-white text-[10px] font-bold flex items-center justify-center" style="background:var(--primary)">{{ $c['unread'] }}</span>
                @endif
            </span>
        </button>
        @endforeach
    </div>
    </x-slot:rail>

{{-- ══ CENTER: the open conversation ══ --}}
<div class="h-full flex flex-col min-h-0 max-w-[52rem] mx-auto w-full">
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm flex flex-col min-h-0 flex-1"
         wire:poll.5s>
        <div class="px-5 py-3 border-b border-gray-100 dark:border-white/[0.05] flex items-center gap-2">
            <b class="text-sm text-gray-900 dark:text-white">{{ $thread === 'team' ? '# Team' : ($current['name'] ?? 'Conversation') }}</b>
            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400">{{ $current['role'] ?? '' }}</span>
            @if($thread === 'team')<span class="text-xs text-gray-400">— everyone on this site sees these</span>@endif
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3 min-h-[40vh]" id="msg-scroll"
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
                                    {{ $mine ? 'text-white rounded-br-md' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-800 dark:text-gray-100 rounded-bl-md' }}"
                             @if($mine) style="background:var(--primary);color:var(--on-primary)" @endif>{{ $m->body }}</div>
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
                      class="bkf-input flex-1 resize-none"></textarea>
            <button type="submit" class="fx px-4 py-2.5 rounded-xl text-sm font-semibold shrink-0" style="background:var(--primary);color:var(--on-primary)">
                <span wire:loading.remove wire:target="send">Send</span>
                <span wire:loading wire:target="send">…</span>
            </button>
        </form>
        @error('body')<p class="px-5 pb-2 text-xs text-red-500">{{ $message }}</p>@enderror
        @endif
    </div>
</div>

    {{-- ══ RIGHT rail: the selected member's profile (or the team summary) ══ --}}
    <x-slot:quick>
        @if ($profile)
            @php $u = $profile['user']; @endphp
            <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-5 mb-4 text-center">
                @php
                    $avatarUrl = ($u->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($u->avatar))
                        ? \Illuminate\Support\Facades\Storage::url($u->avatar) : null;
                @endphp
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $u->name }}" class="w-20 h-20 rounded-full object-cover mx-auto mb-3 ring-4 ring-gray-50 dark:ring-white/[0.06]">
                @else
                    <span class="w-20 h-20 rounded-full grid place-items-center text-2xl font-extrabold text-white mx-auto mb-3" style="background:var(--primary)">{{ $u->initials() }}</span>
                @endif
                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">{{ $u->name }}</h3>
                <p class="text-[12px] text-gray-400">{{ $u->job_title ?: 'Team member' }}</p>
                <span class="inline-block mt-2 px-2.5 py-1 rounded-full text-[11px] font-bold text-white" style="background:var(--primary)">{{ $profile['role'] }}</span>

                <div class="mt-4 space-y-2 text-left">
                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50 dark:border-white/[0.04]">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Email</span>
                        <a href="mailto:{{ $u->email }}" class="text-[12.5px] font-bold hover:underline truncate max-w-[60%]" style="color:var(--primary)">{{ $u->email }}</a>
                    </div>
                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50 dark:border-white/[0.04]">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Messages</span>
                        <span class="text-[12.5px] font-bold text-gray-800 dark:text-gray-100">{{ $profile['messages'] }} between you</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50 dark:border-white/[0.04]">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Last message</span>
                        <span class="text-[12.5px] font-bold text-gray-800 dark:text-gray-100">{{ $profile['last_at']?->diffForHumans() ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Member since</span>
                        <span class="text-[12.5px] font-bold text-gray-800 dark:text-gray-100">{{ $profile['member_since']?->format('M Y') ?? '—' }}</span>
                    </div>
                </div>
            </div>
        @else
            {{-- # Team selected: who's in this room --}}
            <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2"># Team — {{ $this->team->count() }} {{ Str::plural('member', $this->team->count()) }}</h3>
                @foreach ($this->team as $person)
                    <div class="flex items-center gap-2.5 py-1.5 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                        <span class="w-8 h-8 rounded-full grid place-items-center text-[11px] font-bold text-white shrink-0" style="background:var(--primary)">{{ strtoupper(mb_substr($person->name, 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[12.5px] font-bold text-gray-800 dark:text-gray-100 truncate">{{ $person->name }}{{ $person->id === $meId ? ' (you)' : '' }}</span>
                            <span class="block text-[10.5px] text-gray-400 truncate">{{ $this->roleLabels[$person->id] ?? 'member' }}</span>
                        </span>
                        @if($person->id !== $meId)
                            <button wire:click="openThread('{{ $person->id }}')" class="fx text-[11px] font-bold hover:underline shrink-0" style="color:var(--primary)">Message</button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Related --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Related</h3>
            @foreach ([
                ['Team', 'members, roles & permissions', $this->site->name.'/team'],
                ['Tasks', 'assign work to teammates', $this->site->name.'/tasks'],
                ['Alerts', 'team notices land here', $this->site->name.'/alerts'],
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
