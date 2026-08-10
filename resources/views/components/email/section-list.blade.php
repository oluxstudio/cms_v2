@props([
    'sections',        // the current sections array
    'labels',          // key => human label
    'editableKeys',    // keys whose text is editable
    'prefix',          // wire:model path prefix, e.g. "sections" or "fbTemplate.sections"
    'up',              // wire method name for move-up, e.g. "moveSectionUp"
    'down',            // wire method name for move-down
])

<div class="space-y-2">
    @foreach ($sections as $index => $section)
        <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] p-3
                    {{ ($section['enabled'] ?? true) ? '' : 'opacity-55' }}">
            <div class="flex items-center gap-2">
                <div class="flex flex-col">
                    <button wire:click="{{ $up }}({{ $index }})" type="button" @if($index === 0) disabled @endif
                            class="text-gray-400 hover:text-gray-700 disabled:opacity-30 leading-none">▲</button>
                    <button wire:click="{{ $down }}({{ $index }})" type="button" @if($index === count($sections) - 1) disabled @endif
                            class="text-gray-400 hover:text-gray-700 disabled:opacity-30 leading-none">▼</button>
                </div>
                <span class="flex-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $labels[$section['key']] ?? $section['key'] }}</span>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="{{ $prefix }}.{{ $index }}.enabled" class="sr-only peer">
                    <span class="relative h-5 w-9 rounded-full bg-gray-300 dark:bg-white/20 peer-checked:bg-indigo-600 transition-colors
                                 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-4"></span>
                </label>
            </div>

            @if (in_array($section['key'], $editableKeys, true))
                <textarea wire:model.live.debounce.300ms="{{ $prefix }}.{{ $index }}.text" rows="{{ $section['key'] === 'greeting' ? 1 : 3 }}"
                          class="mt-2 w-full px-3 py-2 text-sm rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 resize-none leading-relaxed"></textarea>
            @elseif ($section['key'] === 'logo')
                <p class="mt-2 text-[11px] text-gray-400">Shows the site logo above — or the app logo if none is set.</p>
            @elseif ($section['key'] === 'summary')
                <p class="mt-2 text-[11px] text-gray-400">Automatically lists everything the visitor submitted.</p>
            @endif
        </div>
    @endforeach
</div>

<p class="text-[11px] text-gray-400 mt-2 leading-relaxed">
    Placeholders:
    @foreach(['{name}', '{site}', '{type}', '{field:email}', '{fields}'] as $ph)
        <code class="mx-0.5 px-1.5 py-0.5 rounded bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">{{ $ph }}</code>
    @endforeach
    <br>
    <b>{field:key}</b> inserts one submitted value (e.g. <code class="px-1 rounded bg-gray-100 dark:bg-white/[0.06]">{field:phone}</code>);
    <b>{fields}</b> lists everything they submitted.
</p>
