<div class="h-full flex flex-col">
    <div class="shrink-0 px-4 py-3 border-b border-gray-200 dark:border-white/[0.06] flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg bg-indigo-600 text-white text-sm font-black flex items-center justify-center">P</span>
        <div>
            <p class="text-sm font-bold text-gray-900 dark:text-white leading-none">Polux</p>
            <p class="text-[10px] text-gray-400 mt-0.5">Builds with the same blocks you do</p>
        </div>
    </div>

    {{-- Messages --}}
    <div class="flex-1 overflow-y-auto p-3 space-y-2.5"
         x-data x-init="$el.scrollTop = $el.scrollHeight"
         x-on:bk-chat-scroll.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @forelse($messages as $m)
            <div class="max-w-[92%] rounded-2xl px-3 py-2 text-[12.5px] leading-relaxed whitespace-pre-wrap break-words
                {{ $m['role'] === 'user'
                    ? 'ml-auto bg-indigo-600 text-white rounded-br-sm'
                    : 'mr-auto bg-gray-100 dark:bg-white/[0.06] text-gray-800 dark:text-gray-100 rounded-bl-sm' }}">{{ $m['text'] }}</div>
        @empty
            <div class="text-center pt-10 px-4">
                <p class="text-xs text-gray-400 leading-relaxed">
                    Ask Polux to build or change anything on this page —<br>
                    <span class="italic">“add a pricing section with three plans”</span><br>
                    <span class="italic">“make the selected grid 4 columns”</span><br>
                    <span class="italic">“add a contact form that emails me”</span>
                </p>
            </div>
        @endforelse

        @if($busy)
            <div class="mr-auto bg-gray-100 dark:bg-white/[0.06] rounded-2xl rounded-bl-sm px-3 py-2 inline-flex items-center gap-1">
                <span class="olux-btn-dot"></span><span class="olux-btn-dot" style="animation-delay:.15s"></span><span class="olux-btn-dot" style="animation-delay:.3s"></span>
            </div>
        @endif
    </div>

    {{-- Composer --}}
    <div class="shrink-0 p-3 border-t border-gray-200 dark:border-white/[0.06]">
        @if($selectedId)
            <p class="text-[10px] text-indigo-500 mb-1.5 truncate">◎ acting on the selected block</p>
        @endif
        <div class="flex items-end gap-2">
            <textarea wire:model="draft" rows="2" placeholder="Tell Polux what to build…"
                      wire:keydown.enter.prevent="send"
                      class="flex-1 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-3 py-2 text-gray-800 dark:text-gray-100 resize-none"></textarea>
            <button wire:click="send" @disabled($busy)
                    class="shrink-0 px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-semibold">Send</button>
        </div>
    </div>
</div>

@script
<script>
    // After the user bubble paints, kick the model turn + keep scroll pinned.
    $wire.on('bk-chat-run', () => window.dispatchEvent(new CustomEvent('bk-chat-scroll')));
    Livewire.hook('morph.updated', () => window.dispatchEvent(new CustomEvent('bk-chat-scroll')));
</script>
@endscript
