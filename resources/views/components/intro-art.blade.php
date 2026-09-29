{{--
  Illustrations for the first-login intro slides: a soft blob, a central
  card and floating badge tiles, drawn in SVG with the theme colours so they
  follow light/dark mode and any theme change.

    <x-intro-art variant="welcome|steps|plans|help" />
--}}
@props(['variant' => 'welcome'])

@php
    // Unique ids per instance: identical ids across the slides would all
    // resolve to the first SVG, which stops rendering once its slide is hidden.
    $u = 'ia-'.$variant.'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(5));
    $badge = fn (string $x, string $y, string $rot, string $bg, string $path, string $size = '54') =>
        "<g transform=\"translate($x $y) rotate($rot)\">"
        ."<rect x=\"-".($size / 2)."\" y=\"-".($size / 2)."\" width=\"$size\" height=\"$size\" rx=\"14\" fill=\"$bg\" />"
        ."<rect x=\"-".($size / 2)."\" y=\"-".($size / 2)."\" width=\"$size\" height=\"$size\" rx=\"14\" fill=\"url(#{$u}-shine)\" />"
        ."<g transform=\"scale(".($size / 54 * 1.25).") translate(-12 -12)\" fill=\"none\" stroke=\"#fff\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"$path\"/></g></g>";
    $icons = [
        'palette' => 'M12 3a9 9 0 100 18c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16a5 5 0 005-5c0-4.42-4.03-8-9-8zM6.5 12a1 1 0 110-2 1 1 0 010 2zm3-4a1 1 0 110-2 1 1 0 010 2zm5 0a1 1 0 110-2 1 1 0 010 2zm3 4a1 1 0 110-2 1 1 0 010 2z',
        'inbox' => 'M4 13h4l2 3h4l2-3h4M5 5h14l1 8v5a1 1 0 01-1 1H5a1 1 0 01-1-1v-5l1-8z',
        'calendar' => 'M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z',
        'rocket' => 'M14 4c3-1 6-1 6-1s0 3-1 6l-6 6-5-5 6-6zM8 11l-3 1-2 3 4 1m4 2l1 4 3-2 1-3M9 15l-3 3',
        'check' => 'M5 12l4 4L19 7',
        'globe' => 'M12 3a9 9 0 100 18 9 9 0 000-18zm0 0c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9m0-18C9.5 5.5 8.5 8.5 8.5 12s1 6.5 3.5 9M3.5 9h17M3.5 15h17',
        'star' => 'M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6-4.5-4.2 6.1-.7L12 3z',
        'card' => 'M3 7h18M3 11h18M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z',
        'chat' => 'M8 10h8M8 14h5M4 5h16v11H9l-5 4V5z',
        'book' => 'M4 5a2 2 0 012-2h13v16H6a2 2 0 00-2 2V5zm0 16a2 2 0 012-2h13',
    ];
@endphp

<svg viewBox="0 0 420 340" {{ $attributes->merge(['class' => 'w-full h-auto']) }} role="img"
     aria-label="{{ ['welcome' => 'Your website, forms, bookings and going live', 'steps' => 'A checklist of five steps', 'plans' => 'A plan card', 'help' => 'Help is a message away'][$variant] ?? '' }}">
    <defs>
        <linearGradient id="{{ $u }}-shine" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#fff" stop-opacity=".35" />
            <stop offset=".55" stop-color="#fff" stop-opacity="0" />
        </linearGradient>
        <linearGradient id="{{ $u }}-primary" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" style="stop-color:var(--primary)" />
            <stop offset="1" style="stop-color:var(--secondary)" />
        </linearGradient>
        <filter id="{{ $u }}-shadow" x="-20%" y="-20%" width="140%" height="150%">
            <feDropShadow dx="0" dy="10" stdDeviation="12" flood-color="#000" flood-opacity=".14" />
        </filter>
    </defs>

    @if ($variant === 'welcome')
        {{-- blob + browser window with a site preview --}}
        <path d="M210 30c78 0 150 40 162 110s-40 150-128 168-196-18-214-96S132 30 210 30z" style="fill:var(--primary-soft)" />
        <g filter="url(#{{ $u }}-shadow)">
            <rect x="105" y="85" width="210" height="160" rx="16" fill="#fff" />
            <rect x="105" y="85" width="210" height="30" rx="16" style="fill:var(--foreground)" opacity=".92" />
            <rect x="105" y="100" width="210" height="15" style="fill:var(--foreground)" opacity=".92" />
            <circle cx="124" cy="100" r="4.5" fill="#f87171" /><circle cx="138" cy="100" r="4.5" fill="#fbbf24" /><circle cx="152" cy="100" r="4.5" fill="#34d399" />
            <rect x="122" y="130" width="176" height="52" rx="10" fill="url(#{{ $u }}-primary)" />
            <rect x="134" y="144" width="90" height="9" rx="4.5" fill="#fff" opacity=".95" />
            <rect x="134" y="160" width="60" height="7" rx="3.5" fill="#fff" opacity=".7" />
            <rect x="122" y="192" width="54" height="38" rx="8" fill="#f3f4f6" /><rect x="183" y="192" width="54" height="38" rx="8" fill="#f3f4f6" /><rect x="244" y="192" width="54" height="38" rx="8" fill="#f3f4f6" />
        </g>
        <g filter="url(#{{ $u }}-shadow)">
            {!! $badge('70', '92', '-10', '#ec4899', $icons['palette']) !!}
            {!! $badge('352', '78', '9', 'var(--primary)', $icons['inbox']) !!}
            {!! $badge('58', '238', '8', '#6366f1', $icons['calendar']) !!}
            {!! $badge('358', '250', '-8', '#10b981', $icons['rocket']) !!}
        </g>
        <g transform="translate(248 40) rotate(-6)" filter="url(#{{ $u }}-shadow)">
            <rect x="0" y="0" width="64" height="36" rx="18" fill="#fff" />
            <text x="32" y="24" text-anchor="middle" font-size="17" font-weight="800" style="fill:var(--primary)" font-family="inherit">Hi!</text>
        </g>

    @elseif ($variant === 'steps')
        {{-- star burst + checklist card --}}
        <path d="M210 14l38 96 104-18-72 76 66 90-104-34-32 100-32-100-104 34 66-90-72-76 104 18z" style="fill:var(--primary)" opacity=".9" />
        <g filter="url(#{{ $u }}-shadow)">
            <rect x="120" y="92" width="180" height="170" rx="18" fill="#fff" />
            @foreach ([0, 1, 2, 3, 4] as $n)
                @php $y = 116 + $n * 28; $done = $n < 2; @endphp
                <circle cx="144" cy="{{ $y }}" r="9" style="fill:{{ $done ? '#16a34a' : '#e5e7eb' }}" />
                @if ($done)<path d="M140 {{ $y }}l3 3 5-6" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />@endif
                <rect x="162" y="{{ $y - 5 }}" width="{{ [110, 92, 118, 84, 100][$n] }}" height="10" rx="5" style="fill:{{ $done ? '#d1d5db' : 'var(--foreground)' }}" opacity="{{ $done ? '1' : '.75' }}" />
            @endforeach
        </g>
        <g filter="url(#{{ $u }}-shadow)">
            {!! $badge('92', '70', '-12', '#0ea5e9', $icons['globe'], '48') !!}
            {!! $badge('330', '94', '10', '#ec4899', $icons['palette'], '48') !!}
            {!! $badge('334', '264', '-8', '#10b981', $icons['check'], '48') !!}
        </g>

    @elseif ($variant === 'plans')
        <path d="M210 26c86 0 168 52 168 132S300 316 210 316 40 250 40 164 124 26 210 26z" style="fill:var(--primary-soft)" />
        <g filter="url(#{{ $u }}-shadow)" transform="rotate(-6 210 170)">
            <rect x="96" y="84" width="150" height="190" rx="18" fill="#fff" />
            <rect x="112" y="102" width="70" height="10" rx="5" style="fill:var(--foreground)" opacity=".75" />
            <text x="112" y="146" font-size="30" font-weight="800" font-family="inherit" style="fill:var(--foreground)">£45</text>
            @foreach ([0, 1, 2] as $n)
                <circle cx="118" cy="{{ 172 + $n * 22 }}" r="5" style="fill:var(--primary)" />
                <rect x="130" y="{{ 168 + $n * 22 }}" width="{{ [84, 70, 92][$n] }}" height="8" rx="4" fill="#d1d5db" />
            @endforeach
            <rect x="112" y="238" width="118" height="22" rx="11" style="fill:var(--primary)" />
        </g>
        <g filter="url(#{{ $u }}-shadow)" transform="rotate(7 300 180)">
            <rect x="228" y="118" width="130" height="164" rx="18" fill="url(#{{ $u }}-primary)" />
            <rect x="244" y="136" width="56" height="9" rx="4.5" fill="#fff" opacity=".9" />
            <text x="244" y="178" font-size="26" font-weight="800" font-family="inherit" fill="#fff">Free</text>
            <rect x="244" y="196" width="86" height="7" rx="3.5" fill="#fff" opacity=".6" /><rect x="244" y="212" width="70" height="7" rx="3.5" fill="#fff" opacity=".6" />
        </g>
        <g filter="url(#{{ $u }}-shadow)">
            {!! $badge('84', '62', '-10', '#f59e0b', $icons['star'], '48') !!}
            {!! $badge('356', '66', '10', '#6366f1', $icons['card'], '48') !!}
        </g>

    @else
        {{-- help: speech bubbles + book --}}
        <path d="M210 30c80 0 160 48 160 130s-66 150-156 150S44 246 44 160 130 30 210 30z" style="fill:var(--primary-soft)" />
        <g filter="url(#{{ $u }}-shadow)">
            <path d="M104 96h170a16 16 0 0116 16v84a16 16 0 01-16 16H164l-34 28v-28h-26a16 16 0 01-16-16v-84a16 16 0 0116-16z" fill="#fff" />
            <rect x="112" y="122" width="140" height="10" rx="5" style="fill:var(--foreground)" opacity=".75" />
            <rect x="112" y="142" width="116" height="10" rx="5" fill="#d1d5db" />
            <rect x="112" y="162" width="92" height="10" rx="5" fill="#d1d5db" />
        </g>
        <g filter="url(#{{ $u }}-shadow)">
            <path d="M232 176h110a14 14 0 0114 14v58a14 14 0 01-14 14h-16v24l-28-24h-66a14 14 0 01-14-14v-58a14 14 0 0114-14z" fill="url(#{{ $u }}-primary)" />
            <circle cx="264" cy="220" r="6" fill="#fff" /><circle cx="286" cy="220" r="6" fill="#fff" /><circle cx="308" cy="220" r="6" fill="#fff" />
        </g>
        <g filter="url(#{{ $u }}-shadow)">
            {!! $badge('78', '250', '-8', '#6366f1', $icons['book'], '50') !!}
            {!! $badge('348', '84', '10', '#ec4899', $icons['chat'], '50') !!}
        </g>
        <g filter="url(#{{ $u }}-shadow)" transform="translate(330 128)">
            <circle r="15" fill="#ef4444" />
            <text y="6" text-anchor="middle" font-size="16" font-weight="800" fill="#fff" font-family="inherit">1</text>
        </g>
    @endif
</svg>
