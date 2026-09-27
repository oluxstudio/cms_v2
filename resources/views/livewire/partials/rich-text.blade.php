{{-- Plain ⇄ rich text field. Expects:
       $path   dotted wire path (e.g. "edit.nodes.3.value")
       $value  current string
     Fields already carrying inline HTML open in rich mode; the toggle chip
     switches modes without converting anything — plain mode always shows the
     raw HTML. The rich editor writes innerHTML via $wire.set (debounced). --}}
@php $hasHtml = (bool) preg_match('/<\w+[^>]*>/', (string) $value); @endphp
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
        <textarea wire:model.blur="{{ $path }}" rows="4" class="olx-in"></textarea>
    </template>

    <template x-if="rich">
        <div class="olx-rt" x-data="olxRich('{{ $path }}')" wire:ignore>
            <div class="olx-rt-bar">
                <button type="button" @mousedown.prevent="cmd('bold')" title="Bold"><b>B</b></button>
                <button type="button" @mousedown.prevent="cmd('italic')" title="Italic"><i>I</i></button>
                <button type="button" @mousedown.prevent="cmd('underline')" title="Underline"><u>U</u></button>
                <button type="button" @mousedown.prevent="link()" title="Link">🔗</button>
                <button type="button" @mousedown.prevent="highlight()" title="Accent highlight (template style)"><span class="olx-rt-hl">hl</span></button>
                <button type="button" @mousedown.prevent="cmd('removeFormat')" title="Clear formatting">⌫</button>
            </div>
            <div class="olx-rt-area" contenteditable="true" spellcheck="true"
                 x-init="$el.innerHTML = $wire.get('{{ $path }}') ?? ''"
                 @input.debounce.400ms="push($el)"
                 @blur="push($el)"
                 @paste.prevent="pastePlain($event)"></div>
        </div>
    </template>
</div>
