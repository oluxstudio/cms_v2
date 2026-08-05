{{--
    One row of the BlockKit layers panel, recursing into children.
    Expects: $node (tree array), $selectedId, $catalogue, $depth (int).

    Three node classes:
      · editable page/layout blocks — full select/drag/duplicate/delete
      · `_ro` layout blocks shown on a PAGE canvas — visible but untouchable
        (they belong to the layout; edit them in the Layout View)
      · `content_slot` — the layout's single content section: a dashed
        boundary. On a page it holds the page's editable blocks; in the
        Layout View it is a movable placeholder that cannot be deleted.
--}}
@php
    $isLayout = ($catalogue[$node['type']]['kind'] ?? 'content') === 'layout';
    $isSlot   = $node['type'] === 'content_slot';
    $readonly = (bool) ($node['_ro'] ?? false);
    $locked   = (bool) data_get($node, 'meta.locked', false);
    $isRoot   = $depth === 0;
    $icon = $catalogue[$node['type']]['icon'] ?? match ($node['type']) {
        'container' => '▣', 'flex' => '⇆', 'grid' => '▦', 'masonry' => '▤',
        'form' => '✉', 'button' => '⏺', 'divider' => '—',
        'input' => '⌨', 'textarea' => '¶', 'select' => '▾', 'checkbox' => '☑',
        default => '·',
    };
@endphp

@if($isSlot)
    {{-- The content section: dashed boundary; children are the PAGE's blocks --}}
    <div class="my-1 rounded-lg border-2 border-dashed border-indigo-300 dark:border-indigo-500/40 bg-indigo-50/30 dark:bg-indigo-500/[0.05] py-1"
         @if(! $readonly) data-bk-item data-id="{{ $node['id'] }}" @endif>
        <div class="flex items-center gap-1.5 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-500 dark:text-indigo-300">
            @if(! ($node['_slot'] ?? false))
                <span data-bk-handle class="cursor-grab active:cursor-grabbing" title="Drag to position the content section">⠿</span>
            @endif
            ▣ Content section
            <span class="font-normal normal-case tracking-normal text-gray-400">
                {{ ($node['_slot'] ?? false) ? '— your page blocks' : '— each page\'s blocks render here' }}
            </span>
        </div>
        @if($node['_slot'] ?? false)
            <div data-bk-list data-bkw-list data-parent-id="{{ $node['id'] }}" class="min-h-[6px]">
                @foreach($node['children'] as $child)
                    @include('partials.blockkit-node', ['node' => $child, 'selectedId' => $selectedId, 'catalogue' => $catalogue, 'depth' => $depth + 1])
                @endforeach
            </div>
        @endif
    </div>
@else
<div @unless($isRoot || $readonly) data-bk-item data-id="{{ $node['id'] }}" @endunless>
    <div @unless($readonly) wire:click.stop="select('{{ $node['id'] }}')" @endunless
         class="group flex items-center gap-1.5 rounded-lg pr-1.5 py-1 text-[12.5px] transition-colors
            {{ $readonly
                ? 'opacity-50 cursor-default'
                : ($selectedId === $node['id'] ? 'bg-indigo-100 dark:bg-indigo-500/20 text-indigo-800 dark:text-indigo-200 cursor-pointer' : 'hover:bg-gray-100 dark:hover:bg-white/[0.05] text-gray-700 dark:text-gray-300 cursor-pointer') }}"
         style="padding-left: {{ 6 + $depth * 14 }}px"
         @if($readonly) title="From layout “{{ $node['_layout'] ?? '' }}” — edit it in the Layout View" @endif>
        @unless($isRoot || $readonly)
            <span data-bk-handle class="shrink-0 cursor-grab active:cursor-grabbing text-gray-300 dark:text-gray-600 group-hover:text-gray-500" title="Drag to move">⠿</span>
        @endunless
        <span class="shrink-0 w-4 text-center opacity-60">{{ $icon }}</span>
        <span class="truncate font-medium flex-1">{{ data_get($node, 'meta.label', $node['type']) }}</span>
        @if($readonly)<span class="shrink-0 text-[9px] font-bold uppercase tracking-wide text-gray-400">layout</span>@endif
        @if($locked)<span class="shrink-0 text-[10px]" title="Pinned — the AI cannot modify this block">📌</span>@endif
        @unless($isRoot || $readonly)
            <span class="hidden group-hover:flex items-center gap-0.5 shrink-0">
                <button wire:click.stop="duplicateBlock('{{ $node['id'] }}')" class="w-5 h-5 rounded text-gray-400 hover:text-indigo-500 hover:bg-white dark:hover:bg-white/[0.1]" title="Duplicate">⧉</button>
                <button wire:click.stop="deleteBlock('{{ $node['id'] }}')" class="w-5 h-5 rounded text-gray-400 hover:text-rose-500 hover:bg-white dark:hover:bg-white/[0.1]" title="Delete">✕</button>
            </span>
        @endunless
    </div>

    @if($isLayout)
        <div @unless($readonly) data-bk-list data-bkw-list data-parent-id="{{ $node['id'] }}" @endunless class="min-h-[6px]">
            @foreach($node['children'] as $child)
                @include('partials.blockkit-node', ['node' => $child, 'selectedId' => $selectedId, 'catalogue' => $catalogue, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
@endif
