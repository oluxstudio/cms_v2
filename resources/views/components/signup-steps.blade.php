@props(['current' => 1])
@php $labels = ['Account', 'Verify email', 'Your business', 'Done']; @endphp
<ol {{ $attributes->merge(['class' => 'signup-steps flex items-start justify-between w-full max-w-xl mx-auto text-[10px] sm:text-[11px] font-bold uppercase tracking-wider']) }} aria-label="Signup progress">
    @foreach ($labels as $i => $label)
        @php $n = $i + 1; @endphp
        <li class="relative flex-1 flex flex-col items-center text-center gap-1.5 {{ $n < $current ? 'is-done' : ($n === $current ? 'is-on' : '') }} {{ $n <= $current ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-300 dark:text-gray-600' }}" @if ($n === $current) aria-current="step" @endif>
            @unless ($loop->first)
                <span class="absolute top-3 right-1/2 w-full h-px bg-current opacity-30" style="margin-right:14px" aria-hidden="true"></span>
            @endunless
            <span class="relative z-10 w-6 h-6 rounded-full grid place-items-center text-[10px] bg-white dark:bg-[#1d1e2a] step-bg {{ $n < $current ? 'step-done bg-indigo-600 text-white' : ($n === $current ? 'step-on ring-2 ring-indigo-500' : 'ring-1 ring-gray-200 dark:ring-white/10') }}">{{ $n < $current ? '✓' : $n }}</span>
            <span class="leading-tight px-1">{{ $label }}</span>
        </li>
    @endforeach
</ol>
