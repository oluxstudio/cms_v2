{{-- Plain ⇄ rich text field. Expects:
       $path   dotted wire path (e.g. "edit.nodes.3.value")
       $value  current string
       $blocks (optional) true → also headings / lists / quote (long-form bodies, e.g. posts)
       $rows   (optional) plain-mode textarea height
     Fields already carrying inline HTML open in rich mode; the toggle chip
     switches modes without converting anything — plain mode always shows the
     raw HTML. The rich editor writes innerHTML via $wire.set (debounced). --}}
@php
    // Included partials inherit the caller's variables — only trust the shapes we expect
    // (e.g. the Edit page's editor has its own array $rows).
    $value = is_scalar($value ?? null) ? (string) $value : '';
    $hasHtml = (bool) preg_match('/<\w+[^>]*>/', $value);
    $blocks = ($blocks ?? false) === true;
    $rtRows = is_numeric($rows ?? null) ? (int) $rows : 4;
    // Rich mode is exactly as tall as the plain textarea: same line height (1.5), same rows, same padding.
    // Long-form bodies ($blocks) without an explicit row count keep their own taller sizing.
    $rtHeight = (! $blocks || is_numeric($rows ?? null)) ? '--rt-rows:'.$rtRows : '';
@endphp
<div x-data="{ rich: {{ $hasHtml ? 'true' : 'false' }} }">
    <div class="flex justify-end -mb-1">
        <span class="inline-flex rounded-md overflow-hidden border border-gray-200 dark:border-white/[0.1] text-[10px] font-bold">
            <button type="button" @click="rich = false" :class="! rich ? 'text-white' : 'text-gray-400'"
                    :style="! rich ? 'background:var(--primary)' : ''" class="px-1.5 py-0.5" title="Plain text (shows raw HTML)">Aa</button>
            <button type="button" @click="rich = true" :class="rich ? 'text-white' : 'text-gray-400'"
                    :style="rich ? 'background:var(--primary)' : ''" class="px-1.5 py-0.5" title="Rich text editor">✏️ Rich</button>
        </span>
    </div>

    <template x-if="! rich">
        <textarea wire:model.blur="{{ $path }}" rows="{{ $rtRows }}" class="olx-in {{ $blocks ? 'font-mono text-[11px]' : '' }}"></textarea>
    </template>

    <template x-if="rich">
        <div class="olx-rt" x-data="olxRich('{{ $path }}')" wire:ignore>
            <div class="olx-rt-bar">
                @if ($blocks)
                    <button type="button" @mousedown.prevent="block('h2')" title="Heading">H2</button>
                    <button type="button" @mousedown.prevent="block('h3')" title="Subheading">H3</button>
                    <button type="button" @mousedown.prevent="block('p')" title="Paragraph">¶</button>
                    <button type="button" @mousedown.prevent="cmd('insertUnorderedList')" title="Bulleted list">•≡</button>
                    <button type="button" @mousedown.prevent="cmd('insertOrderedList')" title="Numbered list">1≡</button>
                    <button type="button" @mousedown.prevent="block('blockquote')" title="Quote">❝</button>
                @endif
                <button type="button" @mousedown.prevent="cmd('bold')" title="Bold"><b>B</b></button>
                <button type="button" @mousedown.prevent="cmd('italic')" title="Italic"><i>I</i></button>
                <button type="button" @mousedown.prevent="cmd('underline')" title="Underline"><u>U</u></button>
                <button type="button" @mousedown.prevent="link()" title="Link"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/></svg></button>
                <button type="button" @mousedown.prevent="highlight()" title="Accent highlight (template style)"><span class="olx-rt-hl">hl</span></button>
                <button type="button" @mousedown.prevent="cmd('removeFormat')" title="Clear formatting">⌫</button>
            </div>
            <div class="olx-rt-area {{ $blocks ? 'olx-rt-long' : '' }} {{ $rtHeight ? 'olx-rt-rows' : '' }}" contenteditable="true" spellcheck="true" @if ($rtHeight) style="{{ $rtHeight }}" @endif
                 x-init="$el.innerHTML = $wire.get('{{ $path }}') ?? ''"
                 @input.debounce.400ms="push($el)"
                 @blur="push($el)"
                 @paste.prevent="pastePlain($event)"></div>
        </div>
    </template>
</div>
