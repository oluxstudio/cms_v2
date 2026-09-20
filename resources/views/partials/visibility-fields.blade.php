{{-- Shared scheduled-visibility fields — used by the Components and
     Collections edit modals. Props bind to vis* properties on the host
     Livewire component. All optional; everything blank = always visible. --}}
<div class="rounded-xl border border-gray-100 dark:border-white/[0.06] p-3.5 space-y-3" x-data="{ visOpen: {{ $visSet ? 'true' : 'false' }} }">
    <button type="button" @click="visOpen = ! visOpen" class="w-full flex items-center gap-2 text-left">
        <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-500 dark:text-gray-400">👁 Visibility <span class="font-semibold normal-case tracking-normal text-gray-400">— optional schedule &amp; conditions</span></p>
        @if($visSet)<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">scheduled</span>@endif
        <svg class="w-3.5 h-3.5 ml-auto opacity-60 transition-transform" :class="visOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <div x-show="visOpen" x-collapse x-cloak class="space-y-3">
        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Visible from</label>
                <input wire:model="visFrom" type="datetime-local"
                       class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Visible until</label>
                <input wire:model="visUntil" type="datetime-local"
                       class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1.5">Only on these days <span class="font-normal text-gray-400">(none = every day)</span></label>
            <div class="flex flex-wrap gap-1.5">
                @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $d => $label)
                <label class="cursor-pointer">
                    <input wire:model.live="visDays" type="checkbox" value="{{ $d }}" class="peer sr-only">
                    <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold border border-gray-200 dark:border-white/[0.08] text-gray-500
                                 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600">{{ $label }}</span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Daily from <span class="font-normal text-gray-400">(optional pair)</span></label>
                <input wire:model="visTimeFrom" type="time"
                       class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Daily until</label>
                <input wire:model="visTimeUntil" type="time"
                       class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
            </div>
        </div>

        <label class="flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-300 cursor-pointer">
            <input wire:model="visRequiresContent" type="checkbox" class="rounded">
            Only show when it has content <span class="font-normal text-gray-400">(hides itself when the linked collection is empty)</span>
        </label>

        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">Campaign tag <span class="font-normal text-gray-400">— visible only to visitors arriving via ?promo=tag</span></label>
            <input wire:model="visPromo" type="text" placeholder="e.g. summer26" maxlength="64"
                   class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
        </div>

        <p class="text-[10px] text-gray-400">Times are checked in each visitor's own timezone. Rules on shared header/footer components hide them site-wide. Owners always see everything inside the editor.</p>
    </div>
</div>
