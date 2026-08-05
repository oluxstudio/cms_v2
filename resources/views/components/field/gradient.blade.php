{{--
    Gradient field: the collapsed state PREVIEWS the current gradient as a
    swatch; clicking it expands the builder (angle + colour stops → a
    standard linear-gradient() value) plus a raw-CSS input for power users.
    `value` is the current gradient string (for the preview swatch);
    `apply` is the Livewire save method (defaults to saveInspector).
--}}
@props(['label' => null, 'model' => null, 'value' => '', 'apply' => 'saveInspector', 'hint' => null])
<div x-data="{
        open: false,
        angle: 135,
        stops: [{c:'#6366f1',p:0},{c:'#0ea5e9',p:100}],
        build() { return `linear-gradient(${this.angle}deg, ${this.stops.map(s => `${s.c} ${s.p}%`).join(', ')})` },
        applyBuilt() { $wire.set('{{ $model }}', this.build()).then(() => $wire.call('{{ $apply }}')) },
        clearIt() { $wire.set('{{ $model }}', '').then(() => $wire.call('{{ $apply }}')) },
     }">
    <x-field.wrapper :label="$label" :hint="$hint">
        {{-- Collapsed: gradient previewed in the field — click to expand. --}}
        <button type="button" class="bkf-input bkf-grad-swatch" @click="open = !open" title="Click to edit the gradient">
            <span class="bkf-grad-chip" style="{{ $value !== '' ? 'background-image:'.$value : 'background:repeating-conic-gradient(#e5e7eb 0% 25%, #fff 0% 50%) 0 0/10px 10px' }}"></span>
            <span class="bkf-mono" style="flex:1;min-width:0;text-align:left;font-size:11px;opacity:.8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $value !== '' ? $value : 'none — click to add' }}</span>
            <span x-text="open ? '▴' : '▾'" style="opacity:.5;font-size:10px"></span>
        </button>
    </x-field.wrapper>

    {{-- Expanded: raw value + visual builder. --}}
    <div x-show="open" x-cloak class="bkf-panel">
        <input type="text" @if($model) wire:model="{{ $model }}" @endif class="bkf-input bkf-mono"
               placeholder="linear-gradient(135deg, #6366f1 0%, #0ea5e9 100%)">
        <div style="display:flex;align-items:center;gap:8px">
            <span style="flex:1;height:20px;border-radius:6px;border:1px solid rgba(0,0,0,.1)" :style="`background-image:${build()}`"></span>
            <label class="bkf-hint" style="display:flex;align-items:center;gap:4px;margin:0">angle
                <input type="number" step="15" x-model.number="angle" class="bkf-input" style="width:58px;padding:3px 6px">°
            </label>
        </div>
        <template x-for="(s, i) in stops" :key="i">
            <div style="display:flex;align-items:center;gap:6px">
                <input type="color" x-model="s.c" style="width:30px;height:26px;border-radius:6px;border:1px solid rgba(0,0,0,.12);background:transparent;padding:1px;cursor:pointer">
                <input type="number" min="0" max="100" x-model.number="s.p" class="bkf-input" style="width:58px;padding:3px 6px">
                <span class="bkf-hint" style="margin:0">%</span>
                <button type="button" x-show="stops.length > 2" @click="stops.splice(i, 1)" class="bkf-btn" style="color:#e11d48" title="Remove stop">✕</button>
            </div>
        </template>
        <div style="display:flex;gap:6px">
            <button type="button" class="bkf-btn" @click="stops.push({c:'#ffffff',p:100})">＋ stop</button>
            <button type="button" class="bkf-btn bkf-primary" @click="applyBuilt()">Apply gradient</button>
            <button type="button" class="bkf-btn" @click="clearIt()">Clear</button>
        </div>
    </div>
</div>
