{{--
  One schema-driven property input. Vars: $f (field def), $model (wire path, e.g.
  "values.tagline"), $value, $site.
--}}
@php
    $input = 'w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/[0.04] px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[color:var(--primary)]/40 focus:border-[color:var(--primary)]';
    $ghost = 'fx inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400';
    $id = 'p-'.str_replace('.', '-', $model);
    $err = $errors->first($model);
    $ph = $f['placeholder'] ?? '';
@endphp
<div>
    @unless (($hideLabel ?? false) || $f['input'] === 'toggle')
        <label class="block text-[12px] font-bold text-gray-600 dark:text-gray-300 mb-1" for="{{ $id }}">{{ $f['display'] ?? preg_replace('/^(Address|Review|Hours) /', '', $f['label']) }}</label>
    @endunless

    @switch($f['input'])
        @case('textarea')
            <textarea id="{{ $id }}" wire:model.blur="{{ $model }}" rows="3" class="{{ $input }}" placeholder="{{ $ph }}"></textarea>
            @break
        @case('select')
            <select id="{{ $id }}" wire:model.live="{{ $model }}" class="{{ $input }}">
                @foreach ($f['options'] ?? [] as $ov => $ol)
                    <option value="{{ $ov }}">{{ $ol }}</option>
                @endforeach
            </select>
            @break
        @case('toggle')
            <label class="flex items-start gap-3 cursor-pointer select-none" for="{{ $id }}">
                <span class="relative inline-flex shrink-0 mt-0.5" x-data>
                    <input id="{{ $id }}" type="checkbox" class="peer sr-only"
                           @checked(in_array($value, ['1', 'true', 'on'], true))
                           x-on:change="$wire.set('{{ $model }}', $event.target.checked ? '1' : '0')">
                    <span class="w-10 h-6 rounded-full bg-gray-200 dark:bg-white/15 transition-colors peer-checked:bg-[color:var(--primary)]"></span>
                    <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $f['display'] ?? $f['label'] }}</span>
                    @if (! empty($f['help']))<span class="block text-[11.5px] text-gray-500 dark:text-gray-400">{{ $f['help'] }}</span>@endif
                </span>
            </label>
            @break
        @case('image')
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-16 h-16 rounded-xl border border-dashed border-gray-200 dark:border-white/10 grid place-items-center overflow-hidden bg-gray-50 dark:bg-white/[0.03] shrink-0">
                    @if ($value && ($src = \App\Support\SiteProperties::imageUrl($site, $value)))
                        <img src="{{ $src }}" alt="" class="max-w-full max-h-full object-contain p-1">
                    @else
                        <span class="text-[10px] text-gray-400">None</span>
                    @endif
                </div>
                <div class="flex-1 min-w-[200px] space-y-2">
                    <x-asset-picker :model="$model" :site="$site" type="image" placeholder="Image URL, or pick from assets" />
                    @if ($value)
                        <button type="button" wire:click="$set('{{ $model }}', '')" class="text-xs font-semibold text-rose-500 hover:text-rose-600">Remove</button>
                    @endif
                </div>
            </div>
            @break
        @default
            <input id="{{ $id }}" type="{{ ['number' => 'text', 'tel' => 'tel', 'email' => 'email', 'url' => 'url', 'date' => 'date'][$f['input']] ?? 'text' }}"
                   @if ($f['input'] === 'number') inputmode="decimal" @endif
                   wire:model.blur="{{ $model }}" class="{{ $input }}" placeholder="{{ $ph ?: ($f['input'] === 'url' ? 'https://' : '') }}">
    @endswitch

    @if ($err)<p class="text-xs text-rose-500 mt-1">{{ $err }}</p>@endif
    @if (! empty($f['help']) && ! in_array($f['input'], ['toggle'], true) && ! ($hideLabel ?? false))
        <p class="text-[11px] text-gray-400 mt-1">{{ $f['help'] }}</p>
    @endif
</div>
