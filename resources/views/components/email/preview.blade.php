@props([
    'preview',   // ['subject'=>, 'sections'=>[{key,label,text}], 'sample'=>[..], 'dynamic'=>[key=>payload]]
    'logo',      // resolved logo URL or ''
    'site',
])

@php
    $siteName = ucwords(str_replace('-', ' ', $site->name));
    $dynamic = $preview['dynamic'] ?? [];
@endphp

<div class="rounded-2xl border border-gray-200 dark:border-white/[0.08] overflow-hidden shadow-sm">
    <div class="px-4 py-2.5 bg-gray-50 dark:bg-white/[0.04] border-b border-gray-100 dark:border-white/[0.06]">
        <p class="text-xs text-gray-400">Subject</p>
        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $preview['subject'] }}</p>
    </div>
    <div class="bg-[#f3f4f6] p-5">
        <div class="max-w-md mx-auto bg-white rounded-2xl overflow-hidden shadow-sm">
            @foreach ($preview['sections'] as $section)
                @php $data = $dynamic[$section['key']] ?? null; @endphp
                @switch($section['key'])
                    @case('logo')
                        <div class="px-6 py-4 border-b border-gray-100">
                            @if($logo)<img src="{{ $logo }}" alt="logo" class="h-8 object-contain">@else<span class="text-lg font-extrabold text-gray-900">{{ $siteName }}</span>@endif
                        </div>
                        @break

                    @case('greeting')
                        <div class="px-6 pt-5 pb-1 text-[14px] font-semibold text-gray-900 whitespace-pre-line">{{ $section['text'] }}</div>
                        @break

                    @case('footer')
                        <div class="px-6 py-3 border-t border-gray-100 text-[11px] text-gray-400 whitespace-pre-line">{{ $section['text'] }}</div>
                        @break

                    @case('summary')
                        @if(!empty($preview['sample']))
                        <div class="px-6 py-3">
                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">What you sent</p>
                                @foreach ($preview['sample'] as $k => $v)
                                    <p class="text-[12px] text-gray-600"><b>{{ \Illuminate\Support\Str::headline($k) }}:</b> {{ $v }}</p>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        @break

                    @case('booking_summary')
                        @if($data)
                        <div class="px-6 py-3">
                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 space-y-0.5">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Booking details</p>
                                @if(!empty($data['summary']))<p class="text-[12px] text-gray-600">{{ $data['summary'] }}</p>@endif
                                @if(!empty($data['reference']))<p class="text-[12px] text-gray-600"><b>Reference:</b> {{ $data['reference'] }}</p>@endif
                                @if(!empty($data['total']))<p class="text-[12px] text-gray-600"><b>Total:</b> {{ $data['total'] }}</p>@endif
                                @if(!empty($data['paid']))<p class="text-[12px] text-gray-600"><b>Paid:</b> {{ $data['paid'] }}</p>@endif
                                @if(!empty($data['balance']))<p class="text-[12px] text-gray-600"><b>Balance due:</b> {{ $data['balance'] }}</p>@endif
                            </div>
                        </div>
                        @endif
                        @break

                    @case('invoice_summary')
                        @if($data)
                        <div class="px-6 py-3">
                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Invoice {{ $data['number'] ?? '' }}@if(!empty($data['due'])) · due {{ $data['due'] }}@endif</p>
                                @foreach(($data['items'] ?? []) as $item)
                                    <p class="text-[12px] text-gray-600 flex justify-between"><span>{{ $item['description'] }} × {{ $item['qty'] }}</span><b>{{ $item['amount'] }}</b></p>
                                @endforeach
                                <p class="text-[12px] text-gray-900 flex justify-between font-bold mt-1"><span>Total</span><span>{{ $data['total'] ?? '' }}</span></p>
                            </div>
                            <div class="text-center mt-3"><span class="inline-block px-5 py-2 rounded-lg bg-gray-900 text-white text-[12px] font-bold">View &amp; pay invoice</span></div>
                        </div>
                        @endif
                        @break

                    @case('order_lines')
                        @if($data)
                        <div class="px-6 py-3">
                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Order {{ $data['number'] ?? '' }}</p>
                                @foreach(($data['items'] ?? []) as $item)
                                    <p class="text-[12px] text-gray-600 flex justify-between"><span>{{ $item['qty'] }} × {{ $item['name'] }}</span><b>{{ $item['amount'] }}</b></p>
                                @endforeach
                                <p class="text-[12px] text-gray-900 flex justify-between font-bold mt-1"><span>Total</span><span>{{ $data['total'] ?? '' }}</span></p>
                            </div>
                            <div class="text-center mt-3"><span class="inline-block px-5 py-2 rounded-lg bg-gray-900 text-white text-[12px] font-bold">Track your order</span></div>
                        </div>
                        @endif
                        @break

                    @case('quote_summary')
                        @if($data)
                        <div class="px-6 py-3">
                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Your estimate @if(!empty($data['reference']))· {{ $data['reference'] }}@endif</p>
                                @foreach(($data['results'] ?? []) as $row)
                                    <p class="text-[12px] text-gray-600 flex justify-between"><span>{{ $row['label'] }}</span><b>{{ $row['formatted'] }}</b></p>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        @break

                    @case('review_button')
                    @case('book_button')
                        <div class="px-6 py-3 text-center">
                            <span class="inline-block px-5 py-2 rounded-lg bg-gray-900 text-white text-[12px] font-bold">{{ $data['label'] ?? 'Open' }}</span>
                        </div>
                        @break

                    @default
                        @if(($section['text'] ?? null) !== null)
                            <div class="px-6 py-2 text-[13px] leading-relaxed text-gray-700 whitespace-pre-line">{{ $section['text'] }}</div>
                        @endif
                @endswitch
            @endforeach
        </div>
    </div>
</div>
