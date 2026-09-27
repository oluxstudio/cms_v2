{{--
  Multi-select — chips inside the trigger, "+N" overflow counter, expandable
  panel with search + two-column checkbox list. Binds to an ARRAY property
  on the Livewire component:

    <x-field.multiselect label="Tags" model="tags" :options="['family' => 'Family', ...]" />
--}}
@props(['label' => null, 'model' => null, 'options' => [], 'placeholder' => 'Choose…', 'hint' => null, 'searchable' => true, 'shown' => 3])

<x-field.wrapper :label="$label" :hint="$hint">
    <div x-data="{
            open: false,
            q: '',
            sel: @if($model) $wire.entangle('{{ $model }}') @else [] @endif,
            opts: {{ \Illuminate\Support\Js::from(collect($options)->map(fn ($l, $v) => ['value' => (string) $v, 'label' => (string) $l])->values()) }},
            has(v) { return (this.sel ?? []).map(String).includes(String(v)) },
            toggle(v) {
                this.sel = this.has(v) ? (this.sel ?? []).filter(x => String(x) !== String(v)) : [...(this.sel ?? []), v];
            },
            label(v) { return (this.opts.find(o => String(o.value) === String(v)) ?? {}).label ?? v },
            get filtered() {
                const q = this.q.trim().toLowerCase();
                return q ? this.opts.filter(o => o.label.toLowerCase().includes(q)) : this.opts;
            },
         }"
         class="relative" @click.outside="open = false" @keydown.escape.window="open = false">

        {{-- Trigger: placeholder, or chips + overflow counter --}}
        <button type="button" @click="open = ! open"
                {{ $attributes->merge(['class' => 'bkf-input flex items-center gap-1.5 text-left cursor-pointer']) }}>
            <span x-show="!(sel ?? []).length" class="text-gray-400 font-normal flex-1 truncate">{{ $placeholder }}</span>
            <span x-show="(sel ?? []).length" x-cloak class="flex-1 flex items-center gap-1.5 min-w-0 overflow-hidden">
                <template x-for="v in (sel ?? []).slice(0, {{ (int) $shown }})" :key="v">
                    <span class="bkf-chip" x-text="label(v)"></span>
                </template>
                <template x-if="(sel ?? []).length > {{ (int) $shown }}">
                    <span class="flex items-center gap-1 shrink-0">
                        <span class="text-gray-400 font-bold">…</span>
                        <span class="bkf-chip-count" x-text="(sel ?? []).length - {{ (int) $shown }}"></span>
                    </span>
                </template>
            </span>
            <svg class="w-4 h-4 shrink-0 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>

        {{-- Panel: search + two-column checkbox list --}}
        <div x-show="open" x-cloak x-transition.opacity.duration.120ms
             class="bkf-panel absolute left-0 right-0 top-full mt-2 z-30 max-h-72 overflow-y-auto">
            @if ($searchable)
                <div class="bkf-search mb-2">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input type="text" x-model="q" placeholder="Search" class="bkf-input">
                </div>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                <template x-for="o in filtered" :key="o.value">
                    <label class="bkf-check px-1.5 py-1 rounded-lg hover:bg-gray-50 dark:hover:bg-white/[0.05]">
                        <span class="shrink-0 w-[18px] h-[18px] rounded-md grid place-items-center border transition-colors"
                              :class="has(o.value) ? 'border-transparent' : 'border-gray-300 dark:border-white/20 bg-white dark:bg-white/[0.05]'"
                              :style="has(o.value) ? 'background:var(--primary)' : ''">
                            <svg x-show="has(o.value)" class="w-3 h-3" style="color:var(--on-primary)"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <input type="checkbox" class="sr-only" :checked="has(o.value)" @change="toggle(o.value)">
                        <span class="truncate" x-text="o.label"></span>
                    </label>
                </template>
                <p x-show="! filtered.length" class="col-span-full text-sm text-gray-400 px-1.5 py-2">No matches.</p>
            </div>
        </div>
    </div>
</x-field.wrapper>
