{{-- Read-only render of ANY entry value, recursively, chosen by MediaValue::kind():
     media → preview · media-list → gallery · rows → cards · group → labelled rows · list → bullets.
     Text is always shown as text (values can come from visitor submissions).
     Vars: $value, $siteId, $depth (internal). --}}
@php
    $depth = $depth ?? 0;
    $k = \App\Support\MediaValue::kind($value, $siteId ?? null);
    $plain = fn ($v) => trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li|/h[1-6])\s*/?>#i', "\n", (string) $v))));
@endphp
@if ($value === null || $value === '' || $value === [])
    <span class="text-gray-400">—</span>
@elseif ($k === 'boolean')
    <span class="font-semibold {{ $value ? 'text-emerald-600' : 'text-gray-500' }}">{{ $value ? 'Yes' : 'No' }}</span>
@elseif ($k === 'media')
    @include('livewire.partials.media-preview', ['media' => \App\Support\MediaValue::detect($value, $siteId ?? null), 'size' => $depth ? 'sm' : 'lg'])
    <span class="block mt-1 text-[11px] text-gray-400 font-mono break-all">{{ $value }}</span>
@elseif ($k === 'media-list' || $k === 'media-lines')
    <div class="grid grid-cols-3 gap-2">
        @foreach ($k === 'media-lines' ? \App\Support\MediaValue::lines($value, $siteId ?? null) : $value as $mv)
            @php $m = \App\Support\MediaValue::detect($mv, $siteId ?? null); @endphp
            @if ($m)
                <a href="{{ $m['url'] }}" target="_blank" rel="noopener" title="{{ $mv }}"
                   class="block aspect-square rounded-xl overflow-hidden border border-gray-100 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.04] flex items-center justify-center">
                    @if ($m['kind'] === 'image')
                        <img src="{{ $m['url'] }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.style.visibility='hidden'">
                    @elseif ($m['kind'] === 'video')
                        <video src="{{ $m['url'] }}#t=0.5" preload="metadata" muted class="w-full h-full object-cover bg-black"></video>
                    @else
                        <span class="text-[10px] font-bold uppercase text-gray-500 px-1 text-center break-all">{{ $m['kind'] === 'document' ? 'file' : $m['kind'] }}<br><span class="font-normal normal-case">{{ \Illuminate\Support\Str::limit($m['name'], 18) }}</span></span>
                    @endif
                </a>
            @endif
        @endforeach
    </div>
@elseif ($k === 'rows')
    <div class="space-y-2">
        @foreach ($value as $row)
            <div class="rounded-xl border border-gray-100 dark:border-white/[0.08] p-2.5 space-y-1.5">
                @if (is_array($row))
                    @foreach ($row as $rk => $rv)
                        <div>
                            @unless (is_int($rk))<span class="block text-[10.5px] font-bold uppercase tracking-wider text-gray-400">{{ \Illuminate\Support\Str::headline((string) $rk) }}</span>@endunless
                            @include('livewire.partials.field-value', ['value' => $rv, 'siteId' => $siteId ?? null, 'depth' => $depth + 1])
                        </div>
                    @endforeach
                @else
                    @include('livewire.partials.field-value', ['value' => $row, 'siteId' => $siteId ?? null, 'depth' => $depth + 1])
                @endif
            </div>
        @endforeach
    </div>
@elseif ($k === 'group')
    <dl class="rounded-xl border border-gray-100 dark:border-white/[0.08] p-2.5 space-y-1.5">
        @foreach ($value as $gk => $gv)
            <div>
                <dt class="text-[10.5px] font-bold uppercase tracking-wider text-gray-400">{{ \Illuminate\Support\Str::headline((string) $gk) }}</dt>
                <dd>@include('livewire.partials.field-value', ['value' => $gv, 'siteId' => $siteId ?? null, 'depth' => $depth + 1])</dd>
            </div>
        @endforeach
    </dl>
@elseif ($k === 'list')
    <ul class="list-disc pl-5 space-y-0.5">
        @foreach ($value as $lv)<li>@include('livewire.partials.field-value', ['value' => $lv, 'siteId' => $siteId ?? null, 'depth' => $depth + 1])</li>@endforeach
    </ul>
@else
    <span class="whitespace-pre-line">{{ $plain($value) }}</span>
@endif
