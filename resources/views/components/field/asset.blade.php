{{--
    Asset field: text input (ref/URL) + an icon on the right that opens a
    MODAL to pick from the site's media library. `assets` is a collection of
    Media models; `pick` is the Livewire method called with the chosen ref
    (defaults to the generic pickProp(key, ref)). `kind`: image | video | all.
--}}
@props(['label' => null, 'model' => null, 'assets' => null, 'videos' => null, 'kind' => 'image', 'pick' => null, 'propKey' => null, 'hint' => null])
@php
    $assets = $assets ?? collect();
    $videos = $videos ?? collect();
@endphp
<div x-data="{ open: false }" @keydown.escape.window="open = false">
    <x-field.wrapper :label="$label" :hint="$hint">
        <div class="bkf-affix">
            <input type="text" @if($model) wire:model="{{ $model }}" @endif
                   placeholder="@media/filename or URL" class="bkf-input bkf-mono">
            <button type="button" class="bkf-icon" @click="open = true" title="Choose from the media library">
                @if($kind === 'video')
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="14" height="14" rx="2"/><path d="m22 8-6 4 6 4V8Z"/></svg>
                @else
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                @endif
            </button>
        </div>
    </x-field.wrapper>

    {{-- Asset picker modal --}}
    <div x-show="open" x-cloak class="bkf-modal-backdrop" @click.self="open = false">
        <div class="bkf-modal">
            <div class="bkf-modal-head">
                <h3>{{ $kind === 'video' ? 'Choose a video' : 'Choose an image' }}</h3>
                <button type="button" class="bkf-btn" @click="open = false">✕ Close</button>
            </div>
            @php $pickCall = fn ($ref) => ($pick ?? 'pickProp')."(".($pick ? '' : "'".$propKey."', ")."'".$ref."')"; @endphp
            @if($assets->isEmpty() && $videos->isEmpty())
                <p class="bkf-hint">No media yet — upload assets on the Media page first.</p>
            @endif
            @if($assets->isNotEmpty())
                <div class="bkf-grid">
                    @foreach($assets as $m)
                        <button type="button" class="bkf-tile" wire:click="{{ $pickCall($m->ref()) }}"
                                @click="open = false" title="{{ $m->name }}">
                            <img src="{{ $m->publicUrl() }}" alt="{{ $m->alt_text ?? $m->name }}" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
            @if($videos->isNotEmpty())
                <div class="space-y-1" @if($assets->isNotEmpty()) style="margin-top:8px" @endif>
                    @foreach($videos as $m)
                        <button type="button" class="bkf-row" wire:click="{{ $pickCall($m->ref()) }}"
                                @click="open = false" title="{{ $m->name }}">
                            <span style="flex:none;width:34px;height:24px;border-radius:5px;background:#0d1117;display:grid;place-items:center">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                            <span style="min-width:0">
                                <span style="display:block;font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $m->name }}</span>
                                <span class="bkf-mono" style="display:block;font-size:9px;opacity:.6;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ basename($m->url) }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
