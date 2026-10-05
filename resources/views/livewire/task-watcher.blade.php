{{-- Background-task bell (TaskWatcher): unread count, recent notices, toasts on finish. --}}
<div class="relative shrink-0" wire:poll.10s="poll" x-data @click.outside="$wire.open && $wire.toggle()">
    <button type="button" wire:click="toggle" title="Background tasks"
            class="relative w-9 h-9 flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3C7.7 6.2 6 8.4 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if ($unread)
            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full text-[10.5px] font-bold leading-[18px] text-center text-white"
                  style="background:var(--primary)">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
        <span class="sr-only">{{ $unread }} unread task {{ Str::plural('notice', $unread) }}</span>
    </button>

    @if ($open)
        <div class="fixed left-4 right-4 top-16 sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-2 sm:w-[340px] bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-xl border border-gray-100 dark:border-white/[0.08] overflow-hidden z-50">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-white/[0.06]">
                <p class="text-[13px] font-bold text-gray-900 dark:text-white">Background tasks</p>
                @if ($unread)
                    <button type="button" wire:click="markAllRead" class="text-[12px] font-semibold" style="color:var(--primary)">Mark all read</button>
                @endif
            </div>
            <div class="max-h-[360px] overflow-y-auto divide-y divide-gray-50 dark:divide-white/[0.04]">
                @forelse ($recent as $a)
                    <button type="button" wire:click="openAlert('{{ $a->id }}')"
                            class="w-full text-left flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[0.04] {{ $a->read_at ? 'opacity-70' : '' }}">
                        <span class="mt-1.5 w-2 h-2 rounded-full shrink-0" style="background:{{ $a->level === 'error' ? '#ef4444' : '#22c55e' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-semibold text-gray-900 dark:text-white">{{ $a->title }}</span>
                            @if ($a->body)<span class="block text-[12px] text-gray-500 dark:text-gray-400 leading-snug">{{ Str::limit($a->body, 140) }}</span>@endif
                            <span class="block text-[11px] text-gray-400 mt-0.5">{{ $a->site?->name ? Str::headline($a->site->name).' · ' : '' }}{{ $a->created_at->diffForHumans() }}</span>
                        </span>
                        @unless ($a->read_at)<span class="mt-1.5 w-1.5 h-1.5 rounded-full shrink-0" style="background:var(--primary)"></span>@endunless
                    </button>
                @empty
                    <p class="px-4 py-6 text-center text-[12.5px] text-gray-500 dark:text-gray-400">When something you start runs in the background — a template build, a design install, a mailbox — you'll be told here when it finishes.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
