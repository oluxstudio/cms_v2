{{--
  One component field in the Edit page's component editor.
  Vars: $i (index in edit.nodes), $node, $site, $def (Site Properties schema entry or null).
--}}
@php
    $path = "edit.nodes.$i.value";
    $input = $def['input'] ?? null;
    $builtIn = $def !== null && ! ($def['repeaterRow'] ?? false);
@endphp
<div class="rounded-lg border border-gray-100 dark:border-white/[0.06] p-2" wire:key="node-{{ $node['id'] ?? 'new-'.$i }}"
     data-node-field="{{ \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($node['label'])) }}">
    <div class="flex items-center gap-1.5">
        @if (empty($node['id']))
            <input wire:model="edit.nodes.{{ $i }}.label" class="olx-in !mt-0" placeholder="Field label">
            {{-- .live so switching type immediately swaps the input (asset picker / collection link) --}}
            <select wire:model.live="edit.nodes.{{ $i }}.type" class="olx-in !mt-0 !w-24">
                @foreach (\App\Models\Node::TYPES as $nt)
                    <option value="{{ $nt }}">{{ $nt === 'collection' ? 'collection (list)' : $nt }}</option>
                @endforeach
            </select>
        @else
            <span class="flex-1 text-[11px] text-gray-400" @if ($def && ($def['display'] ?? null)) title="{{ $node['label'] }}" @endif>{{ ($def['repeaterRow'] ?? false) ? $node['label'] : ($def['display'] ?? $node['label']) }} @unless ($def)<span class="opacity-60">({{ $node['type'] }})</span>@endunless</span>
        @endif
        @unless ($builtIn)
            <button wire:click="removeNode({{ $i }})" class="text-[11px] text-rose-500 shrink-0">Remove</button>
        @endunless
    </div>

    @if ($node['type'] === 'collection')
        @if ($node['value'] && ($card = ($edit['lists'] ?? [])[(string) $node['value']] ?? null))
            {{-- A collection grid inside this component --}}
            <div class="mt-1">
                @include('livewire.partials.connect-collection-card', ['card' => $card, 'title' => '▦ '.$card['name']])
            </div>
        @elseif ($node['value'])
            <button wire:click="openLinkedCollection('{{ $node['value'] }}')"
                    class="mt-1 w-full text-left text-[11px] font-semibold px-2 py-1.5 rounded-lg text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-white/[0.05] hover:bg-indigo-100 dark:hover:bg-white/[0.08]">
                Open items →
            </button>
        @else
            <p class="mt-1 text-[10px] text-gray-400">Save the component — the list is created and linked automatically.</p>
        @endif
    @elseif ($node['type'] === 'image')
        <div class="flex items-center gap-1.5">
            @php
                $imgSrc = \App\Models\Media::resolveRef($site->id, (string) $node['value']);
                // Asset fields can hold a video (hero backgrounds) — preview it as one.
                $isVideo = (bool) preg_match('#\.(mp4|webm|mov)(\?|$)#i', $imgSrc ?: (string) $node['value']);
            @endphp
            @if ($imgSrc !== '' && $isVideo)
                <video src="{{ $imgSrc }}" muted playsinline preload="metadata" class="w-9 h-9 rounded-lg object-cover shrink-0 border border-gray-100 dark:border-white/[0.08]"></video>
            @elseif ($imgSrc !== '')
                <img src="{{ $imgSrc }}" alt="" class="w-9 h-9 rounded-lg object-cover shrink-0 border border-gray-100 dark:border-white/[0.08]">
            @endif
            <input type="text" wire:model="{{ $path }}" class="olx-in !mt-0 flex-1 min-w-0" placeholder="https://… or @media/file">
            <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'connect', nodeIndex: {{ $i }}, type: '{{ $isVideo ? 'video' : 'image' }}' } })"
                    class="shrink-0 px-2 py-1.5 rounded-lg text-[11px] font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-white/[0.06] hover:bg-gray-200 dark:hover:bg-white/[0.1]"
                    title="Choose from the asset library">Assets</button>
        </div>
    @elseif ($node['type'] === 'boolean')
        <label class="mt-1 inline-flex items-center gap-2 cursor-pointer select-none" x-data>
            <span class="relative inline-flex">
                <input type="checkbox" class="peer sr-only" @checked(in_array($node['value'], ['1', 'true', 'on', 'yes'], true))
                       x-on:change="$wire.set('{{ $path }}', $event.target.checked ? '1' : '0', false)">
                <span class="w-9 h-5 rounded-full bg-gray-200 dark:bg-white/15 transition-colors peer-checked:bg-[color:var(--primary)]"></span>
                <span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
            </span>
            <span class="text-[11px] text-gray-500">{{ in_array($node['value'], ['1', 'true', 'on', 'yes'], true) ? 'On' : 'Off' }}</span>
        </label>
    @elseif ($input === 'select')
        <select wire:model="{{ $path }}" class="olx-in !mt-1">
            @foreach ($def['options'] ?? [] as $ov => $ol)
                <option value="{{ $ov }}">{{ $ol }}</option>
            @endforeach
        </select>
    @elseif ($input === 'textarea')
        <textarea wire:model="{{ $path }}" rows="3" class="olx-in !mt-1" placeholder="{{ $def['placeholder'] ?? '' }}"></textarea>
    @elseif ($def !== null || in_array($node['type'], ['number', 'url'], true))
        {{-- Plain values (numbers, links, and every Site Properties text field) — never rich text. --}}
        <input type="{{ $node['type'] === 'url' ? 'url' : ($input === 'email' ? 'email' : ($input === 'date' ? 'date' : 'text')) }}"
               @if ($node['type'] === 'number') inputmode="decimal" @endif
               wire:model="{{ $path }}" class="olx-in !mt-1"
               placeholder="{{ $def['placeholder'] ?? ($node['type'] === 'url' ? 'https://' : ($input === 'hours' ? '09:00-17:00 or Closed' : '')) }}">
    @else
        @include('livewire.partials.rich-text', ['path' => $path, 'value' => $node['value'] ?? ''])
    @endif
    @if (! empty($def['help']))
        <p class="mt-1 text-[10px] text-gray-400">{{ $def['help'] }}</p>
    @endif
</div>
