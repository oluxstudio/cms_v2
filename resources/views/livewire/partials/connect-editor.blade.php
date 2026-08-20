{{-- Inline editor for a committed content model (shared by ingestion + content modes). Expects $edit + $site. --}}
@php $t = $edit['type'] ?? null; @endphp

@if ($t === 'component')
    <div class="mt-4 flex items-center justify-between">
        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Fields</p>
        <button wire:click="addNode" class="text-xs font-semibold" style="color:var(--primary)">+ Add field</button>
    </div>
    <div class="mt-1.5 space-y-2">
        @foreach ($edit['nodes'] as $i => $node)
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
                        <span class="flex-1 text-[11px] text-gray-400">{{ $node['label'] }} <span class="opacity-60">({{ $node['type'] }})</span></span>
                    @endif
                    <button wire:click="removeNode({{ $i }})" class="text-[11px] text-rose-500 shrink-0">Remove</button>
                </div>
                @if ($node['type'] === 'collection')
                    @if ($node['value'])
                        <button wire:click="openLinkedCollection('{{ $node['value'] }}')"
                                class="mt-1 w-full text-left text-[11px] font-semibold px-2 py-1.5 rounded-lg text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-white/[0.05] hover:bg-indigo-100 dark:hover:bg-white/[0.08]">
                            Open items → ({{ \App\Models\Collection::where('site_id', $site->id)->find($node['value'])?->items()->count() ?? 0 }})
                        </button>
                    @else
                        <p class="mt-1 text-[10px] text-gray-400">Save the component — the list is created and linked automatically.</p>
                    @endif
                @elseif ($node['type'] === 'image')
                    <div class="flex items-center gap-1.5">
                        @php $imgSrc = \App\Models\Media::resolveRef($site->id, (string) $node['value']); @endphp
                        @if ($imgSrc !== '')
                            <img src="{{ $imgSrc }}" alt="" class="w-9 h-9 rounded-lg object-cover shrink-0 border border-gray-100 dark:border-white/[0.08]">
                        @endif
                        <input type="text" wire:model="edit.nodes.{{ $i }}.value" class="olx-in !mt-0 flex-1 min-w-0" placeholder="https://… or @media/file">
                        <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'connect', nodeIndex: {{ $i }} } })"
                                class="shrink-0 px-2 py-1.5 rounded-lg text-[11px] font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-white/[0.06] hover:bg-gray-200 dark:hover:bg-white/[0.1]"
                                title="Choose from the asset library">Assets</button>
                    </div>
                @else
                    <textarea wire:model="edit.nodes.{{ $i }}.value" rows="2" class="olx-in"></textarea>
                @endif
            </div>
        @endforeach
    </div>
    <button wire:click="saveComponent" class="olx-save">Save component</button>

@elseif ($t === 'collection')
    {{-- Item schema: the fields every item carries; extendable any time --}}
    <div class="mt-4 flex items-center gap-1.5">
        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider shrink-0">Item fields</span>
        <span class="text-[11px] text-gray-400 truncate flex-1">{{ implode(' · ', $edit['schema']) ?: 'none yet' }}</span>
    </div>
    {{-- New field = label + type + default; the default is written onto EVERY existing item and used for new ones --}}
    <div class="mt-1 space-y-1">
        <div class="flex items-center gap-1.5">
            <input wire:model="newField.label" wire:keydown.enter="addCollectionField" placeholder="Field label, e.g. Photo"
                   class="olx-in !mt-0 flex-1 min-w-0">
            <select wire:model.live="newField.type" class="olx-in !mt-0 !w-24 shrink-0">
                @foreach (\App\Livewire\ConnectReviewPage::ITEM_FIELD_TYPES as $ft)
                    <option value="{{ $ft }}">{{ $ft }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-1.5">
            <input wire:model="newField.default" wire:keydown.enter="addCollectionField"
                   placeholder="Default value (applied to all items)" class="olx-in !mt-0 flex-1 min-w-0">
            @if (($newField['type'] ?? '') === 'image')
                <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'connect-new-field' } })"
                        class="shrink-0 px-1.5 py-1 rounded-lg text-[10px] font-semibold text-gray-500 dark:text-gray-300 bg-gray-100 dark:bg-white/[0.06] hover:bg-gray-200 dark:hover:bg-white/[0.1]">Assets</button>
            @endif
            <button wire:click="addCollectionField" class="shrink-0 text-xs font-semibold px-2 py-1.5 rounded-lg" style="color:var(--primary)">+ Add field</button>
        </div>
    </div>

    <div class="mt-3 flex items-center justify-between">
        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Items ({{ count($edit['items']) }})</p>
        <button wire:click="addItem" class="text-xs font-semibold" style="color:var(--primary)">+ Add item</button>
    </div>
    <div class="mt-1.5 space-y-3" data-items-list>
        @foreach ($edit['items'] as $i => $item)
            <div data-item-row class="rounded-lg border border-gray-100 dark:border-white/[0.06] p-2">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] text-gray-400">#{{ $i + 1 }}</span>
                    <button wire:click="removeItem({{ $i }})" class="text-[11px] text-rose-500">Remove</button>
                </div>
                @foreach ($edit['schema'] as $key)
                    <label class="block mb-1">
                        <span class="text-[11px] text-gray-400">{{ $key }}</span>
                        <span class="flex items-center gap-1.5">
                            <input wire:model="edit.items.{{ $i }}.data.{{ $key }}" class="olx-in !mt-0 flex-1 min-w-0">
                            <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'connect', itemIndex: {{ $i }}, itemKey: '{{ $key }}' } })"
                                    class="shrink-0 px-1.5 py-1 rounded-lg text-[10px] font-semibold text-gray-500 dark:text-gray-300 bg-gray-100 dark:bg-white/[0.06] hover:bg-gray-200 dark:hover:bg-white/[0.1]"
                                    title="Choose from the asset library">Assets</button>
                        </span>
                    </label>
                @endforeach
            </div>
        @endforeach
    </div>
    <button wire:click="saveCollection" class="olx-save">Save collection</button>

@elseif ($t === 'form')
    <label class="block mt-4">
        <span class="text-[11px] text-gray-400">Title</span>
        <input wire:model="edit.title" class="olx-in">
    </label>
    <label class="block mt-2">
        <span class="text-[11px] text-gray-400">Submit endpoint</span>
        <input type="url" wire:model="edit.endpoint" class="olx-in" placeholder="Blank = capture in CRM (form responses)">
        <span class="text-[10px] text-gray-400">Leave blank so submissions are saved as form responses in this CMS.</span>
    </label>

    <div class="mt-3 flex items-center justify-between">
        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Fields</p>
        <button wire:click="addFormField" class="text-xs font-semibold" style="color:var(--primary)">+ Add field</button>
    </div>
    <div class="mt-1.5 space-y-2">
        @foreach ($edit['fields'] as $i => $field)
            <div class="rounded-lg border border-gray-100 dark:border-white/[0.06] p-2">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] text-gray-400">#{{ $i + 1 }}</span>
                    <button wire:click="removeFormField({{ $i }})" class="text-[11px] text-rose-500">Remove</button>
                </div>
                <input wire:model="edit.fields.{{ $i }}.label" class="olx-in" placeholder="Label">
                <div class="grid grid-cols-2 gap-1.5 mt-1">
                    <input wire:model="edit.fields.{{ $i }}.key" class="olx-in" placeholder="key">
                    <select wire:model="edit.fields.{{ $i }}.type" class="olx-in">
                        @foreach (['text', 'email', 'tel', 'textarea', 'number', 'select', 'checkbox', 'date'] as $ft)
                            <option value="{{ $ft }}">{{ $ft }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-1.5 mt-1 text-[11px] text-gray-500">
                    <input type="checkbox" wire:model="edit.fields.{{ $i }}.required"> required
                </label>
            </div>
        @endforeach
    </div>
    <button wire:click="saveForm" class="olx-save">Save form</button>
    <a href="{{ route('site.forms', ['siteID' => $site->name]) }}" wire:navigate
       class="mt-2 block text-center text-xs font-semibold" style="color:var(--primary)">
        Open in Forms (details + responses) →
    </a>

@elseif ($t === 'post')
    <label class="block mt-4"><span class="text-[11px] text-gray-400">Title</span>
        <input wire:model="edit.title" class="olx-in"></label>
    <label class="block mt-2"><span class="text-[11px] text-gray-400">Intro</span>
        <textarea wire:model="edit.excerpt" rows="2" class="olx-in"></textarea></label>
    <label class="block mt-2"><span class="text-[11px] text-gray-400">Content (HTML)</span>
        <textarea wire:model="edit.body" rows="6" class="olx-in font-mono text-[11px]"></textarea></label>
    <button wire:click="savePost" class="olx-save">Save post</button>
    <a href="{{ route('site.posts', ['siteID' => $site->name]) }}" wire:navigate
       class="mt-2 block text-center text-xs font-semibold" style="color:var(--primary)">
        Open in Posts →
    </a>
@endif
