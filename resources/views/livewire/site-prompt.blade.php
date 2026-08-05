<div class="h-full flex flex-col" id="misso-root"
     x-data="poluxChat()" x-init="init()">

    {{-- ── Chat messages ── --}}
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3" id="misso-chat" x-ref="chat">

        @if(empty($messages))
            {{-- Empty state --}}
            <div class="h-full flex flex-col items-center justify-center py-10 text-center">
                <div class="w-12 h-12 rounded-2xl bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-1">Ask Polux anything</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">Use AI to manage this site</p>
                <div class="flex flex-col gap-2 w-full text-left">
                    @foreach([
                        'when was this site created?',
                        'create a page called About Us',
                        'create a contact form with name, email and message',
                    ] as $s)
                        <button type="button" wire:click="$set('input', '{{ $s }}')"
                                class="text-left text-xs text-gray-600 dark:text-gray-300 bg-white dark:bg-white/[0.05] hover:bg-gray-50 dark:hover:bg-white/[0.08] border border-gray-200 dark:border-white/10 rounded-xl px-3 py-2.5 transition-colors">
                            {{ $s }}
                        </button>
                    @endforeach
                </div>
            </div>
        @else
            @foreach($messages as $m)
                @if($m['role'] === 'user')
                    {{-- User bubble — right --}}
                    <div class="flex justify-end gap-2 items-end">
                        <div class="max-w-[80%]">
                            <p class="text-[10px] text-gray-400 text-right mb-1 pr-1">You</p>
                            <div class="bg-indigo-600 text-white text-sm rounded-2xl rounded-br-none px-4 py-2.5 leading-relaxed">
                                {{ $m['text'] }}
                            </div>
                        </div>
                        <div class="shrink-0 w-7 h-7 rounded-full bg-indigo-700 flex items-center justify-center text-[11px] font-bold text-white">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </div>
                    </div>
                @else
                    {{-- AI bubble — left --}}
                    <div class="flex gap-2 items-end">
                        <div class="shrink-0 w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-indigo-500 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        </div>
                        <div class="max-w-[80%]">
                            <p class="text-[10px] text-gray-400 mb-1 pl-1">Polux</p>
                            <div class="text-sm rounded-2xl rounded-bl-none px-4 py-2.5 leading-relaxed whitespace-pre-line
                                {{ ($m['ok'] ?? true)
                                    ? 'bg-white dark:bg-white/[0.07] border border-gray-100 dark:border-white/10 text-gray-800 dark:text-gray-100'
                                    : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300' }}">
                                {{ $m['text'] }}
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Loading indicator while send() runs --}}
            <div wire:loading wire:target="send" class="flex gap-2 items-end">
                <div class="shrink-0 w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-500 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>
                <div class="bg-white dark:bg-white/[0.07] border border-gray-100 dark:border-white/10 rounded-2xl rounded-bl-none px-4 py-3">
                    <div class="flex gap-1">
                        <span class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                        <span class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                        <span class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                    </div>
                </div>
            </div>
        @endif

    </div>

    {{-- ── Input bar ── --}}
    <div class="shrink-0 border-t border-gray-200 dark:border-white/[0.06] p-3">

        {{-- Status row --}}
        <div class="flex items-center justify-between mb-2">
            @if(count($messages) > 0)
                <button type="button" wire:click="clearChat"
                        class="text-[11px] text-gray-400 hover:text-red-500 flex items-center gap-1 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Clear
                </button>
            @else
                <span></span>
            @endif

            @php
                $configured = \App\Services\SiteAgent::configured();
                $online     = $configured && \App\Services\SiteAgent::reachable();
                $driver     = config('services.llm.driver', 'anthropic');
                $label      = match(true) {
                    !$configured        => 'no AI key',
                    $driver==='deepseek'=> config('services.deepseek.model','deepseek-chat'),
                    $driver==='ollama'  => config('services.ollama.model','ollama'),
                    default             => 'claude',
                };
            @endphp
            <span class="text-[10px] font-medium flex items-center gap-1.5
                {{ $online ? 'text-emerald-500' : ($configured ? 'text-amber-500' : 'text-gray-400') }}">
                <span class="w-1.5 h-1.5 rounded-full
                    {{ $online ? 'bg-emerald-500' : ($configured ? 'bg-amber-400' : 'bg-gray-400') }}"></span>
                {{ $online ? $label : ($configured ? $label.' (offline)' : $label) }}
            </span>
        </div>

        {{-- Input form --}}
        <form wire:submit="send" @submit="$nextTick(() => scrollBottom('smooth'))">
            <div class="flex gap-2 items-end bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/10 rounded-2xl px-3 py-2.5 focus-within:border-indigo-400 dark:focus-within:border-indigo-500 transition-colors">
                <textarea
                    wire:model="input"
                    rows="1"
                    placeholder="Ask Polux…  e.g. create a page called About"
                    class="flex-1 bg-transparent text-sm text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 resize-none focus:outline-none leading-relaxed"
                    style="max-height:120px"
                    onInput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.closest('form').requestSubmit()}"
                ></textarea>
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="send"
                        class="shrink-0 w-8 h-8 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 flex items-center justify-center transition-colors">
                    <svg wire:loading.remove wire:target="send" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-7 7m7-7l7 7"/>
                    </svg>
                    <svg wire:loading wire:target="send" class="w-4 h-4 text-white animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>

@script
<script>
    Alpine.data('poluxChat', () => ({
        init() {
            const el = this.$refs.chat;
            if (!el) return;

            // Jump to the bottom on first paint (existing history).
            this.$nextTick(() => this.scrollBottom('auto'));

            // Any DOM change inside the thread (new prompt, thinking dots,
            // AI reply streaming in) pins the view to the bottom.
            this._observer = new MutationObserver(() => this.scrollBottom('smooth'));
            this._observer.observe(el, { childList: true, subtree: true, characterData: true });

            // Livewire re-sent the messages array — scroll after the patch lands.
            this.$wire.on('chat-updated', () => this.$nextTick(() => this.scrollBottom('smooth')));
        },

        scrollBottom(behavior = 'smooth') {
            const el = this.$refs.chat;
            if (el) el.scrollTo({ top: el.scrollHeight, behavior });
        },

        destroy() {
            this._observer?.disconnect();
        },
    }));
</script>
@endscript
