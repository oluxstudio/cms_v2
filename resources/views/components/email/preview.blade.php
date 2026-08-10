@props([
    'preview',   // ['subject'=>, 'sections'=>[{key,label,text}], 'sample'=>[..]]
    'logo',      // resolved logo URL or ''
    'site',
])

@php $siteName = ucwords(str_replace('-', ' ', $site->name)); @endphp

<div class="rounded-2xl border border-gray-200 dark:border-white/[0.08] overflow-hidden shadow-sm">
    <div class="px-4 py-2.5 bg-gray-50 dark:bg-white/[0.04] border-b border-gray-100 dark:border-white/[0.06]">
        <p class="text-xs text-gray-400">Subject</p>
        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $preview['subject'] }}</p>
    </div>
    <div class="bg-[#f3f4f6] p-5">
        <div class="max-w-md mx-auto bg-white rounded-2xl overflow-hidden shadow-sm">
            @foreach ($preview['sections'] as $section)
                @switch($section['key'])
                    @case('logo')
                        <div class="px-6 py-4 border-b border-gray-100">
                            @if($logo)<img src="{{ $logo }}" alt="logo" class="h-8 object-contain">@else<span class="text-lg font-extrabold text-gray-900">{{ $siteName }}</span>@endif
                        </div>
                        @break
                    @case('greeting')
                        <div class="px-6 pt-5 pb-1 text-[14px] font-semibold text-gray-900 whitespace-pre-line">{{ $section['text'] }}</div>
                        @break
                    @case('intro')
                        <div class="px-6 py-2 text-[13px] leading-relaxed text-gray-700 whitespace-pre-line">{{ $section['text'] }}</div>
                        @break
                    @case('summary')
                        <div class="px-6 py-3">
                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">What you sent</p>
                                @foreach ($preview['sample'] as $k => $v)
                                    <p class="text-[12px] text-gray-600"><b>{{ \Illuminate\Support\Str::headline($k) }}:</b> {{ $v }}</p>
                                @endforeach
                            </div>
                        </div>
                        @break
                    @case('footer')
                        <div class="px-6 py-3 border-t border-gray-100 text-[11px] text-gray-400 whitespace-pre-line">{{ $section['text'] }}</div>
                        @break
                @endswitch
            @endforeach
        </div>
    </div>
</div>
