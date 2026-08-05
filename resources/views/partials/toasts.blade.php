{{-- Global toast stack. Fire one anywhere with:
       Livewire:  $this->dispatch('toast', level:'success', title:'Success', message:'…')
       Browser:   window.dispatchEvent(new CustomEvent('toast',{detail:{level,title,message}}))
--}}
<div x-data="{
        toasts: [],
        meta: {
            success: { color:'#16a34a', dot:'#22c55e', icon:'M5 13l4 4L19 7' },
            error:   { color:'#dc2626', dot:'#ef4444', icon:'M12 9v3m0 4h.01M10.3 4.3 1.8 18a1.7 1.7 0 0 0 1.5 2.6h17.4A1.7 1.7 0 0 0 22.2 18L13.7 4.3a1.7 1.7 0 0 0-2.9 0Z' },
            warning: { color:'#d97706', dot:'#f59e0b', icon:'M12 9v3m0 4h.01M10.3 4.3 1.8 18a1.7 1.7 0 0 0 1.5 2.6h17.4A1.7 1.7 0 0 0 22.2 18L13.7 4.3a1.7 1.7 0 0 0-2.9 0Z' },
            info:    { color:'#2563eb', dot:'#3b82f6', icon:'M12 8h.01M11 12h1v4h1' },
        },
        add(d) {
            const id = Date.now() + Math.random();
            const t  = { id, level: d.level || 'info', title: d.title || 'Notice', message: d.message || '', timeout: d.timeout || 4500 };
            this.toasts.push(t);
            setTimeout(() => this.remove(id), t.timeout);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
     }"
     @toast.window="add($event.detail)"
     class="fixed top-4 right-4 z-[9998] w-[340px] max-w-[calc(100vw-2rem)] space-y-2.5 pointer-events-none">

    <template x-for="t in toasts" :key="t.id">
        <div x-show="true"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-6"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-6"
             class="pointer-events-auto relative overflow-hidden rounded-2xl shadow-xl
                    bg-white dark:bg-[#0b0d14] border border-gray-100 dark:border-white/[0.06]">

            <div class="flex items-start gap-3 px-4 py-3 pr-9">
                {{-- icon disc --}}
                <span class="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center shrink-0"
                      :style="'background:'+meta[t.level].dot">
                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="meta[t.level].icon"/>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-bold leading-tight" :style="'color:'+meta[t.level].color" x-text="t.title"></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-snug mt-0.5 break-words" x-text="t.message"></p>
                </div>
            </div>

            {{-- close --}}
            <button @click="remove(t.id)" type="button"
                    class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>

            {{-- animated progress bar --}}
            <div class="h-1 w-full" style="background:transparent">
                <div class="h-full"
                     :style="'background:'+meta[t.level].dot+'; animation: toast-bar '+t.timeout+'ms linear forwards'"></div>
            </div>
        </div>
    </template>
</div>
