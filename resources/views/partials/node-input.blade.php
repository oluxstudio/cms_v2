{{-- One editable node row: type badge + label + value input.
     Expects $node; optional $readonlyLabels (app blocks: labels are the
     contract with the published template — renaming would silently detach the
     field from the renderer, so they display as static text). --}}
@php
    $readonlyLabels = $readonlyLabels ?? false;
    $isHtmlNode = str_starts_with((string) ($node->description ?? ''), 'html');
@endphp
<div class="flex items-start gap-2.5">
    <span class="shrink-0 mt-2 w-14 text-[9px] font-bold uppercase tracking-wide text-center px-1 py-0.5 rounded
        {{ match($node->type) {
            'text' => 'bg-blue-100 dark:bg-blue-500/15 text-blue-600 dark:text-blue-300',
            'url' => 'bg-violet-100 dark:bg-violet-500/15 text-violet-600 dark:text-violet-300',
            'number' => 'bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-300',
            'boolean' => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-300',
            'color' => 'bg-pink-100 dark:bg-pink-500/15 text-pink-600 dark:text-pink-300',
            'image' => 'bg-teal-100 dark:bg-teal-500/15 text-teal-600 dark:text-teal-300',
            default => 'bg-gray-100 dark:bg-white/[0.08] text-gray-500',
        } }}">{{ $node->type }}</span>
    <div class="flex-1 min-w-0 space-y-1">
        @if($readonlyLabels)
            <p class="w-full text-[11px] font-semibold text-gray-500 dark:text-gray-400 px-0 py-0.5 truncate">{{ $node->label }}</p>
        @else
            <input wire:model="nodeForm.{{ $node->id }}.label" type="text"
                   class="w-full text-[11px] font-semibold text-gray-500 dark:text-gray-400 bg-transparent border-0 border-b border-transparent hover:border-gray-200 dark:hover:border-white/10 focus:border-indigo-400 focus:outline-none px-0 py-0.5">
        @endif
        @if($isHtmlNode)
            <textarea wire:model="nodeForm.{{ $node->id }}.value" rows="2" spellcheck="false"
                      class="w-full text-sm font-mono rounded-lg bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-3 py-2 text-gray-800 dark:text-gray-100 resize-y"></textarea>
            <p class="text-[10px] text-gray-400">Supports HTML — tags like <code class="font-mono">&lt;span&gt;</code> keep the template's styling.</p>
        @elseif($node->type === 'boolean')
            <select wire:model="nodeForm.{{ $node->id }}.value" class="w-full text-sm rounded-lg bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-3 py-2 text-gray-800 dark:text-gray-100">
                <option value="true">Yes</option><option value="false">No</option>
            </select>
        @elseif($node->type === 'color')
            <input wire:model="nodeForm.{{ $node->id }}.value" type="color" class="w-16 h-9 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-transparent cursor-pointer">
        @elseif($node->type === 'number')
            <input wire:model="nodeForm.{{ $node->id }}.value" type="number" class="w-full text-sm rounded-lg bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-3 py-2 text-gray-800 dark:text-gray-100">
        @elseif($node->type === 'image')
            {{-- Image field: resolved live preview + URL/@media input + picker modal --}}
            @php
                $rawImg = (string) ($nodeForm[$node->id]['value'] ?? $node->value);
                $imgSiteId = $siteId ?? ($node->component->site_id ?? 0);
                $resolvedImg = $rawImg !== '' ? \App\Models\Media::resolveRef($imgSiteId, $rawImg) : '';
                // Template /assets/… files aren't served by the CMS — placeholder.
                if (str_starts_with($resolvedImg, '/assets/')) { $resolvedImg = ''; }
            @endphp
            <div class="flex items-center gap-2">
                @if($resolvedImg !== '')
                    <img src="{{ $resolvedImg }}" alt=""
                         class="w-10 h-10 rounded-lg object-cover border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.04]">
                @elseif($rawImg !== '')
                    <span class="w-10 h-10 shrink-0 flex items-center justify-center rounded-lg border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.04]" title="Template asset — shown in the preview">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 12h.008v.008H18V12zm-9-3.75h.008v.008H9V8.25zM3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                    </span>
                @endif
                <input wire:model="nodeForm.{{ $node->id }}.value" type="text" placeholder="Image URL or @media/filename…"
                       class="flex-1 min-w-0 text-sm rounded-lg bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-3 py-2 text-gray-800 dark:text-gray-100">
                <button type="button" @click="$dispatch('open-media-picker', { context: { nodeId: {{ $node->id }} } })"
                        class="shrink-0 px-2.5 py-2 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-white/[0.06] hover:bg-gray-200 dark:hover:bg-white/[0.1]"
                        title="Choose from the media library">Media</button>
            </div>
        @elseif(in_array($node->type, ['text']) && \Illuminate\Support\Str::contains(strtolower($node->label), ['body','description','bio','intro','answer','quote','mission','story','summary','address']))
            <textarea wire:model="nodeForm.{{ $node->id }}.value" rows="2" class="w-full text-sm rounded-lg bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-3 py-2 text-gray-800 dark:text-gray-100 resize-y"></textarea>
        @else
            <input wire:model="nodeForm.{{ $node->id }}.value" type="text" class="w-full text-sm rounded-lg bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] px-3 py-2 text-gray-800 dark:text-gray-100">
        @endif
    </div>
</div>
