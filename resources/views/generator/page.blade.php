@php
    /** @var \App\Models\Site $site */
    /** @var \App\Models\Page $page */
    /** @var array            $navLinks  [['name','url','filename'], ...] */
    /** @var string           $accentColor */

    // Lighten the accent for hover states (just bump opacity)
    $accent = $accentColor;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $page->name }} | {{ $site->name }}</title>
<style>
/* ── Reset ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:#111827;background:#fff;line-height:1.6}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}

/* ── Colours ── */
:root{
  --accent:{{ $accent }};
  --dark:#1e2235;
  --muted:#6b7280;
  --border:#e5e7eb;
  --bg-light:#f9fafb;
}

/* ── Layout ── */
.container{max-width:1100px;margin:0 auto;padding:0 24px}

/* ── Header / Nav ── */
header{background:var(--dark);position:sticky;top:0;z-index:100;box-shadow:0 1px 0 rgba(255,255,255,.06)}
.nav-inner{display:flex;align-items:center;gap:2rem;height:60px}
.nav-logo{display:flex;align-items:center;gap:.5rem;flex-shrink:0}
.nav-logo-icon{width:28px;height:28px;border-radius:8px;background:var(--accent);display:flex;align-items:center;justify-content:center}
.nav-logo-icon svg{width:16px;height:16px;stroke:#fff;fill:none}
.nav-logo-name{color:#fff;font-weight:700;font-size:.9rem;letter-spacing:-.01em}
.nav-links{display:flex;gap:.25rem;flex:1}
.nav-link{color:rgba(255,255,255,.55);font-size:.8rem;font-weight:500;padding:.4rem .8rem;border-radius:8px;transition:background .15s,color .15s}
.nav-link:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-link.active{color:#fff}
.nav-cta{margin-left:auto;background:var(--accent);color:#fff;font-size:.8rem;font-weight:700;padding:.45rem 1.1rem;border-radius:8px;white-space:nowrap;flex-shrink:0;transition:opacity .15s}
.nav-cta:hover{opacity:.88}

/* ── Sections ── */
.page-section{padding:56px 0;border-bottom:1px solid var(--border)}
.page-section:last-child{border-bottom:none}
.section-tag{font-size:.65rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--accent);margin-bottom:1rem}

/* ── Typography ── */
.t-hero{font-size:clamp(2rem,5vw,3.25rem);font-weight:900;line-height:1.12;color:#111827;letter-spacing:-.03em}
.t-headline{font-size:clamp(1.5rem,3vw,2.25rem);font-weight:800;line-height:1.2;color:#111827;letter-spacing:-.02em}
.t-sub{font-size:1.0625rem;color:var(--muted);line-height:1.65;max-width:600px}
.t-body{font-size:.9375rem;color:var(--muted);line-height:1.7}
.t-number{font-size:2.5rem;font-weight:900;color:var(--accent);line-height:1}
.t-label{font-size:.8rem;color:var(--muted);margin-top:.25rem}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:.4rem;background:var(--accent);color:#fff;font-weight:700;font-size:.875rem;padding:.625rem 1.5rem;border-radius:10px;transition:opacity .15s;cursor:pointer}
.btn:hover{opacity:.88}
.btn-ghost{background:transparent;color:var(--accent);border:1.5px solid var(--accent)}
.btn-ghost:hover{background:var(--accent);color:#fff}

/* ── Badge ── */
.badge{display:inline-block;font-size:.7rem;font-weight:700;padding:.2rem .65rem;border-radius:99px;background:rgba(99,102,241,.12);color:var(--accent);letter-spacing:.02em}
.badge-green{background:#dcfce7;color:#16a34a}
.badge-gray{background:#f3f4f6;color:#6b7280}

/* ── Color swatch ── */
.swatch{display:inline-flex;align-items:center;gap:.4rem}
.swatch-dot{width:18px;height:18px;border-radius:5px;border:1px solid rgba(0,0,0,.1);flex-shrink:0}
.swatch-hex{font-size:.75rem;font-family:monospace;color:var(--muted)}

/* ── Card grid ── */
.card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1.25rem;margin-top:1.75rem}
.card{border:1px solid var(--border);border-radius:14px;padding:1.5rem;background:#fff}
.card-icon{width:40px;height:40px;border-radius:10px;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;margin-bottom:1rem}
.card-title{font-size:.9375rem;font-weight:700;color:#111827;margin-bottom:.35rem}
.card-body{font-size:.85rem;color:var(--muted);line-height:1.6}

/* ── Stats row ── */
.stats-row{display:flex;gap:2rem;flex-wrap:wrap;margin-top:1.5rem}
.stat{text-align:center;flex:1;min-width:100px}

/* ── CTA banner ── */
.cta-banner{background:var(--accent);border-radius:20px;padding:3rem 2rem;text-align:center}
.cta-banner .t-headline,.cta-banner .t-sub{color:#fff}
.cta-banner .t-sub{opacity:.85}

/* ── Footer ── */
footer{background:var(--dark);padding:2rem 0}
.footer-inner{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.footer-name{color:rgba(255,255,255,.7);font-size:.8rem}
.footer-badge{font-size:.7rem;color:rgba(255,255,255,.35);letter-spacing:.05em}

/* ── Responsive ── */
@media(max-width:640px){
  .nav-links{display:none}
  .stats-row{gap:1rem}
  .cta-banner{padding:2rem 1rem}
}
</style>
</head>
<body>

{{-- ── Navigation ── --}}
<header>
  <div class="container">
    <nav class="nav-inner">
      <div class="nav-logo">
        <div class="nav-logo-icon">
          <svg viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
          </svg>
        </div>
        <span class="nav-logo-name">{{ $site->name }}</span>
      </div>
      <div class="nav-links">
        @foreach($navLinks as $link)
          <a href="{{ $link['filename'] }}"
             class="nav-link {{ $link['url'] === $page->url ? 'active' : '' }}">
            {{ $link['name'] }}
          </a>
        @endforeach
      </div>
      {{-- If there's a CTA-type URL node on the page, wire it up --}}
      @php
        $ctaNode = $page->components
            ->flatMap->nodes
            ->first(fn($n) => $n->type === 'url' && str_contains(strtolower($n->label), 'cta'));
      @endphp
      @if($ctaNode)
        <a href="{{ $ctaNode->value }}" class="nav-cta">
          {{ $page->components->flatMap->nodes->first(fn($n) => str_contains(strtolower($n->label), 'cta') && $n->type === 'text')?->value ?? 'Get Started' }}
        </a>
      @endif
    </nav>
  </div>
</header>

{{-- ── Page sections ── --}}
<main>
@foreach($page->components as $component)
@php
  $nodes    = $component->nodes->sortBy('order');
  $byLabel  = fn(string $kw) => $nodes->first(fn($n) => str_contains(strtolower($n->label), $kw));
  $headline = $byLabel('headline') ?? $byLabel('title') ?? $byLabel('name');
  $sub      = $byLabel('subheadline') ?? $byLabel('subtitle') ?? $byLabel('sub') ?? $byLabel('body') ?? $byLabel('bio') ?? $byLabel('story') ?? $byLabel('intro') ?? $byLabel('description') ?? $byLabel('mission') ?? $byLabel('excerpt');
  $ctaLabel = $byLabel('cta label') ?? $byLabel('cta primary') ?? $byLabel('button') ?? $byLabel('cta');
  $ctaUrl   = $nodes->first(fn($n) => $n->type === 'url' && !str_contains(strtolower($n->label), 'avatar') && !str_contains(strtolower($n->label), 'twitter') && !str_contains(strtolower($n->label), 'linkedin'));
  $colorNode = $nodes->first(fn($n) => $n->type === 'color');
  $numbers  = $nodes->where('type', 'number');
  $booleans = $nodes->where('type', 'boolean');
  $isBanner = $colorNode && str_contains(strtolower($component->name), 'banner') || str_contains(strtolower($component->name), 'cta');
  $bgStyle  = $isBanner && $colorNode ? "background:{$colorNode->value}" : '';
@endphp

<section class="page-section" style="{{ $bgStyle ? $bgStyle.';color:#fff;' : '' }}">
  <div class="container">
    <p class="section-tag" style="{{ $bgStyle ? 'color:rgba(255,255,255,.6)' : '' }}">
      {{ $component->name }}
    </p>

    {{-- Headline --}}
    @if($headline)
      @if($loop->first)
        <h1 class="t-hero" style="{{ $bgStyle ? 'color:#fff' : '' }}">{{ $headline->value }}</h1>
      @else
        <h2 class="t-headline" style="{{ $bgStyle ? 'color:#fff' : '' }}">{{ $headline->value }}</h2>
      @endif
    @endif

    {{-- Subheadline / body --}}
    @if($sub)
      <p class="t-sub" style="{{ $bgStyle ? 'color:rgba(255,255,255,.8);' : '' }} margin-top:.75rem">
        {!! nl2br(e($sub->value)) !!}
      </p>
    @endif

    {{-- Number stats --}}
    @if($numbers->isNotEmpty())
      <div class="stats-row" style="margin-top:1.5rem">
        @foreach($numbers as $num)
        <div class="stat">
          <div class="t-number" style="{{ $bgStyle ? 'color:#fff' : '' }}">{{ $num->value }}</div>
          <div class="t-label" style="{{ $bgStyle ? 'color:rgba(255,255,255,.6)' : '' }}">{{ $num->label }}</div>
        </div>
        @endforeach
      </div>
    @endif

    {{-- Feature cards — components with multiple text nodes but no headline --}}
    @php
      $featureNodes = $nodes->filter(fn($n) =>
        $n->type === 'text' &&
        $n !== $headline &&
        $n !== $sub &&
        $n !== $ctaLabel &&
        !str_contains(strtolower($n->label), 'badge') &&
        !str_contains(strtolower($n->label), 'section') &&
        !str_contains(strtolower($n->label), 'label') &&
        !str_contains(strtolower($n->label), 'tag')
      );
      $showGrid = $featureNodes->count() >= 4 && !$headline;
    @endphp
    @if($showGrid)
      <div class="card-grid">
        @foreach($featureNodes->chunk(2) as $pair)
        <div class="card">
          <div class="card-icon">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:var(--accent)">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          @foreach($pair as $fn)
            @if($loop->first)
              <p class="card-title">{{ $fn->value }}</p>
            @else
              <p class="card-body">{{ $fn->value }}</p>
            @endif
          @endforeach
        </div>
        @endforeach
      </div>
    @elseif($featureNodes->isNotEmpty() && !$showGrid)
      {{-- Flat text nodes --}}
      @foreach($featureNodes as $fn)
        @if($fn !== $headline && $fn !== $sub && $fn !== $ctaLabel)
        <p class="t-body" style="{{ $bgStyle ? 'color:rgba(255,255,255,.75)' : '' }} margin-top:.5rem">
          {{ $fn->value }}
        </p>
        @endif
      @endforeach
    @endif

    {{-- Color swatch (standalone) --}}
    @if($colorNode && !$isBanner)
      <div class="swatch" style="margin-top:.75rem">
        <span class="swatch-dot" style="background:{{ $colorNode->value }}"></span>
        <span class="swatch-hex">{{ $colorNode->value }}</span>
      </div>
    @endif

    {{-- Boolean badges --}}
    @foreach($booleans as $bool)
      <span class="badge {{ $bool->value === 'true' ? 'badge-green' : 'badge-gray' }}" style="margin-top:.75rem;margin-right:.35rem">
        {{ $bool->label }}: {{ $bool->value === 'true' ? 'Yes' : 'No' }}
      </span>
    @endforeach

    {{-- URL nodes as standalone links (not CTA) --}}
    @php
      $linkNodes = $nodes->filter(fn($n) =>
        $n->type === 'url' &&
        !str_contains(strtolower($n->label), 'cta') &&
        !str_contains(strtolower($n->label), 'bg') &&
        $n->value
      );
    @endphp
    @if($linkNodes->isNotEmpty())
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.75rem">
        @foreach($linkNodes as $link)
          <a href="{{ $link->value }}" class="t-body" target="_blank" rel="noopener noreferrer"
             style="color:var(--accent);text-decoration:underline;font-size:.85rem">
            {{ ucwords(str_replace(['https://', 'http://', 'mailto:', 'www.'], '', $link->label)) }}
          </a>
        @endforeach
      </div>
    @endif

    {{-- CTA button --}}
    @if($ctaLabel && $ctaUrl)
      <div style="margin-top:1.5rem">
        <a href="{{ $ctaUrl->value }}"
           class="btn {{ $isBanner ? 'btn-ghost' : '' }}"
           style="{{ $isBanner ? 'border-color:#fff;color:#fff' : '' }}">
          {{ $ctaLabel->value }}
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
          </svg>
        </a>
      </div>
    @elseif($ctaLabel)
      <div style="margin-top:1.5rem">
        <span class="btn" style="{{ $isBanner ? 'background:rgba(255,255,255,.15)' : '' }}">
          {{ $ctaLabel->value }}
        </span>
      </div>
    @endif

  </div>
</section>
@endforeach
</main>

{{-- ── Footer ── --}}
<footer>
  <div class="container">
    <div class="footer-inner">
      <span class="footer-name">{{ $site->name }}@if($site->domain) · {{ $site->domain }}@endif</span>
      <span class="footer-badge">Generated with Olux CMS</span>
    </div>
  </div>
</footer>

</body>
</html>
