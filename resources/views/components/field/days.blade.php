{{--
    Weekday picker — toggling chips bound to a comma-separated day string
    ("mon,wed,fri") on the Livewire property `model`. Empty string = no
    override (falls back to the site/service schedule).
--}}
@props(['label' => null, 'model', 'hint' => null])
<x-field.wrapper :label="$label" :hint="$hint">
    <div x-data="{
            raw: $wire.entangle('{{ $model }}'),
            all: ['mon','tue','wed','thu','fri','sat','sun'],
            get list() { return String(this.raw ?? '').split(',').map(s => s.trim().toLowerCase()).filter(Boolean) },
            toggle(d) {
                const picked = this.list.includes(d)
                    ? this.list.filter(x => x !== d)
                    : [...this.list, d];
                this.raw = this.all.filter(x => picked.includes(x)).join(',');
            },
        }"
        class="flex flex-wrap gap-1"
        {{ $attributes }}>
        <template x-for="d in all" :key="d">
            <button type="button" @click="toggle(d)"
                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold capitalize transition-colors"
                    :class="list.includes(d)
                        ? 'bg-indigo-600 text-white'
                        : 'bg-gray-50 dark:bg-white/[0.04] text-gray-400 border border-gray-200 dark:border-white/[0.08]'"
                    x-text="d"></button>
        </template>
    </div>
</x-field.wrapper>
