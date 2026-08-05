{{--
    WYSIWYG canvas node — blocks render as REAL page elements on the white
    artboard. Containers are transparent with a dashed blueprint border; leaves
    (headings, buttons, media…) look like the finished page. Hover/select shows
    an outline + a small type tag. Native HTML5 drag & drop:
      · data-bkw-item + data-id  → draggable node
      · data-bkw-list + data-parent-id → container drop target
    Layout blocks on a page canvas carry `_ro` (dimmed, untouchable).
    Expects: $node, $selectedId, $catalogue, $depth.
--}}
@php
    $def      = $catalogue[$node['type']] ?? null;
    $kind     = $def['kind'] ?? 'content';
    $isCont   = in_array($kind, ['layout', 'slot'], true) || in_array($node['type'], ['form'], true);
    $isSlot   = $node['type'] === 'content_slot';
    $readonly = (bool) ($node['_ro'] ?? false);
    $isRoot   = $depth === 0;
    $p        = $node['props'] ?? [];
    $s        = $node['style'] ?? [];

    // PARITY: identical token scales to BlockRenderer.vue — the canvas shows
    // exactly what the preview shows (PAD/GAP/RAD/TOKENS all match).
    $padMap = ['none' => '0', 'sm' => '12px', 'md' => '28px', 'lg' => '56px', 'xl' => '96px'];
    $gapMap = ['none' => '0', 'sm' => '8px', 'md' => '20px', 'lg' => '40px'];
    $radMap = ['none' => '0', 'sm' => '6px', 'md' => '12px', 'lg' => '20px', 'full' => '9999px'];
    $tokMap = ['neutral-50' => '#f8fafc', 'neutral-100' => '#f1f5f9', 'neutral-200' => '#e2e8f0', 'neutral-800' => '#1e293b', 'neutral-900' => '#0f172a', 'accent' => 'var(--accent, #6366f1)', 'surface' => 'var(--surface, #f8fafc)'];
    $col = fn (?string $v) => $v === null || $v === '' ? $v : ($tokMap[$v] ?? $v);
    // renderer parity: panel surface defaults (bg neutral-50, radius md, optional border).
    $panelCss = $node['type'] === 'panel'
        ? 'background:'.$col($p['background'] ?? 'neutral-50').';border-radius:'.($radMap[$p['radius'] ?? 'md'] ?? '12px').';'.(($p['bordered'] ?? false) ? 'border:1px solid rgba(0,0,0,.12);' : '')
        : '';
    $pad = $padMap[$p['padding'] ?? ''] ?? (is_numeric($p['padding'] ?? null) ? ($p['padding'].'px') : '16px');
    $gap = $gapMap[$p['gap'] ?? ''] ?? (is_numeric($p['gap'] ?? null) ? ($p['gap'].'px') : '16px');

    // Container layout CSS — mirrors real CSS semantics so what you arrange is
    // what renders. Grid columns are device-reactive → Alpine x-bind below.
    $css = '';
    // A container with display:grid IS a grid; with display:flex IS a flexbox —
    // the display option unlocks the same properties as the dedicated blocks.
    $isGridLike = in_array($node['type'], ['grid', 'masonry'], true)
        || ($node['type'] === 'container' && ($p['display'] ?? '') === 'grid');
    if ($isCont && ! $isGridLike) {
        if ($node['type'] === 'flex' || ($p['display'] ?? '') === 'flex') {
            // A flex block IS display:flex — every prop maps 1:1 onto CSS.
            // Mobile-first responsive direction: string, or {base, md, lg} —
            // the device toggle previews each breakpoint (like grid columns).
            $fd = $p['direction'] ?? 'row';
            $fdResp = is_array($fd);
            $fdBase = $fdResp ? ($fd['base'] ?? 'column') : $fd;
            $fdMd   = $fdResp ? ($fd['md'] ?? $fdBase) : $fd;
            $fdLg   = $fdResp ? ($fd['lg'] ?? $fdMd) : $fd;
            $css .= 'display:flex;';
            if (! $fdResp) $css .= "flex-direction:{$fdBase};";
            $css .= 'flex-wrap:'.($p['flex_wrap'] ?? 'nowrap').';'; // CSS default: nowrap
            if (($p['justify_content'] ?? '') !== '') $css .= 'justify-content:'.$p['justify_content'].';';
            if (($p['align_items'] ?? '') !== '')     $css .= 'align-items:'.$p['align_items'].';';
            if (($p['align_content'] ?? '') !== '')   $css .= 'align-content:'.$p['align_content'].';';
            $css .= "gap:{$gap};";
        } else {
            // Normal block flow: children stack (flex-column keeps gap working).
            $css .= "display:flex;flex-direction:column;gap:{$gap};";
        }
    }
    $css .= "padding:{$pad};";
    if (($p['radius'] ?? '') !== '') $css .= 'border-radius:'.($radMap[$p['radius']] ?? $p['radius']).';';
    if (($p['max_width'] ?? '') === 'xl') $css .= 'max-width:1120px;margin-left:auto;margin-right:auto;';

    // Background stack, exactly like CSS: overlay tint → gradient → colour.
    $bg = $p['background'] ?? ($s['background'] ?? null);
    $hexA = function (?string $c, int $pct): ?string {
        if (! $c) return null;
        $pct = max(0, min(100, $pct));
        if (preg_match('/^#([0-9a-f]{6})$/i', trim($c), $m)) {
            [$r, $g, $b2] = array_map('hexdec', str_split($m[1], 2));
            return "rgba({$r},{$g},{$b2},".round($pct / 100, 2).')';
        }
        // $theme-variables / named colours: opacity still applies via color-mix
        // (a raw variable would otherwise paint FULLY OPAQUE over the image).
        return "color-mix(in srgb, {$c} {$pct}%, transparent)";
    };
    $bgLayers = [];
    if ($ov = $hexA($p['overlay'] ?? null, (int) ($p['overlay_opacity'] ?? 50))) {
        $bgLayers[] = "linear-gradient({$ov},{$ov})";
    }
    // Gradient overlay: a second tint layer with its own opacity.
    if (($p['overlay_gradient'] ?? '') !== '') {
        $bgLayers[] = \App\Support\Css::fadeGradient($p['overlay_gradient'], (int) ($p['overlay_gradient_opacity'] ?? 50));
    }
    if (($p['gradient'] ?? '') !== '') $bgLayers[] = $p['gradient'];
    // background-image from the media library (or a URL) — bottom layer.
    if (($p['bg_image'] ?? '') !== '') {
        $bgSrc = str_starts_with($p['bg_image'], '@media/')
            ? \App\Models\Media::resolveRef($site->id ?? 0, $p['bg_image'])
            : $p['bg_image'];
        if ($bgSrc) $bgLayers[] = "url('".str_replace(["'", '"', '\\'], '', $bgSrc)."')";
    }
    $bgCss = '';
    if ($bgLayers) $bgCss .= 'background-image:'.implode(',', $bgLayers).';';
    // Precedence: a background IMAGE or GRADIENT wins over the colour — the
    // colour only paints when neither is set (overlay is a tint, not a base).
    $hasBgLayer = (($p['gradient'] ?? '') !== '') || (($p['bg_image'] ?? '') !== '');
    if ($bg && $bg !== 'transparent' && ! $hasBgLayer) $bgCss .= 'background-color:'.$col($bg).';';
    // CSS filters: blur (px) + percentage filters — emitted only when set.
    $filters = '';
    if (($p['blur'] ?? '') !== '') $filters .= 'blur('.max(0, (int) $p['blur']).'px) ';
    foreach (['brightness', 'contrast', 'saturate', 'grayscale'] as $fk) {
        if (($p[$fk] ?? '') !== '') $filters .= "{$fk}(".max(0, (int) $p[$fk]).'%) ';
    }
    if ($filters !== '') $bgCss .= 'filter:'.trim($filters).';';
    // backdrop-filter: blur — frosts what shows THROUGH the block.
    if (($p['backdrop_blur'] ?? '') !== '') {
        $bb = 'blur('.max(0, (int) $p['backdrop_blur']).'px)';
        $bgCss .= "backdrop-filter:{$bb};-webkit-backdrop-filter:{$bb};";
    }
    // The rest of the CSS background longhand set — emitted only when set.
    foreach (['bg_size' => 'background-size', 'bg_position' => 'background-position', 'bg_repeat' => 'background-repeat',
              'bg_attachment' => 'background-attachment', 'bg_clip' => 'background-clip', 'bg_origin' => 'background-origin',
              'bg_blend' => 'background-blend-mode'] as $bk => $bprop) {
        if (($p[$bk] ?? '') !== '') $bgCss .= "{$bprop}:{$p[$bk]};";
    }
    if (($p['bg_clip'] ?? '') === 'text') $bgCss .= '-webkit-background-clip:text;color:transparent;-webkit-text-fill-color:transparent;';
    // z-index needs a stacking context — but an EXPLICIT position prop always wins.
    $zCss = ($p['z_index'] ?? '') !== ''
        ? ((($p['position'] ?? '') === '' ? 'position:relative;' : '').'z-index:'.((int) $p['z_index']).';')
        : '';
    // Container size/spacing PROPS — manual units or $theme-variables.
    $dimCss = '';
    if (($p['width'] ?? '') !== '')  $dimCss .= 'width:'.$p['width'].';';
    if (($p['height'] ?? '') !== '') $dimCss .= 'height:'.$p['height'].';';
    if (is_array($p['margin'] ?? null)) {
        $dimCss .= 'margin:'.implode(' ', array_map(fn ($sd) => ($p['margin'][$sd] ?? '') !== '' ? $p['margin'][$sd] : '0', ['top', 'right', 'bottom', 'left'])).';';
    }
    // Position exactly like CSS: static/relative/absolute/sticky/fixed + inset offsets.
    if (($p['position'] ?? '') !== '') $dimCss .= 'position:'.$p['position'].';';
    foreach ((array) ($p['inset'] ?? []) as $sd => $v) {
        if ($v !== '' && $v !== null && in_array($sd, ['top', 'right', 'bottom', 'left'], true)) $dimCss .= "{$sd}:{$v};";
    }
    if (! in_array($p['display'] ?? '', ['', 'flex', 'grid'], true)) $dimCss .= 'display:'.$p['display'].';';
    if (($p['overflow'] ?? '') !== '') $dimCss .= 'overflow:'.$p['overflow'].';';
    if (($p['opacity'] ?? '') !== '') $dimCss .= 'opacity:'.(max(0, min(100, (int) $p['opacity'])) / 100).';';

    // ── Per-block sizing & spacing (style.width/height/margin/padding) — the
    //    canvas previews it exactly as the renderer will apply it.
    $sideCss = function ($v) use ($padMap) {
        if (is_string($v)) return $padMap[$v] ?? $v;                 // legacy token shorthand
        if (! is_array($v)) return null;
        return implode(' ', array_map(fn ($side) => $padMap[$v[$side] ?? ''] ?? ($v[$side] ?? '0'), ['top', 'right', 'bottom', 'left']));
    };
    $sizeCss = '';
    if (($s['width'] ?? '') !== '')  $sizeCss .= 'width:'.$s['width'].';';
    if (($s['height'] ?? '') !== '') $sizeCss .= 'height:'.$s['height'].';';
    foreach (['min_width' => 'min-width', 'max_width' => 'max-width', 'min_height' => 'min-height', 'max_height' => 'max-height'] as $sk => $cssProp) {
        if (($s[$sk] ?? '') !== '') $sizeCss .= "{$cssProp}:{$s[$sk]};";
    }
    if (($m = $sideCss($s['margin'] ?? null)) !== null)  $sizeCss .= "margin:{$m};";
    if (($pd = $sideCss($s['padding'] ?? null)) !== null) $sizeCss .= "padding:{$pd};";
    // Positioning on ANY block — style.position/inset/z_index/opacity, like CSS.
    if (($s['position'] ?? '') !== '') $sizeCss .= 'position:'.$s['position'].';';
    foreach ((array) ($s['inset'] ?? []) as $sd => $v) {
        if ($v !== '' && $v !== null && in_array($sd, ['top', 'right', 'bottom', 'left'], true)) $sizeCss .= "{$sd}:{$v};";
    }
    if (($s['z_index'] ?? '') !== '') {
        $sizeCss .= (($s['position'] ?? ($p['position'] ?? '')) === '' ? 'position:relative;' : '').'z-index:'.((int) $s['z_index']).';';
    }
    if (($s['opacity'] ?? '') !== '') $sizeCss .= 'opacity:'.(max(0, min(100, (int) $s['opacity'])) / 100).';';
    if (($s['overflow'] ?? '') !== '') $sizeCss .= 'overflow:'.$s['overflow'].';';
    // Text colour on ANY block — children INHERIT it unless they set their own (real CSS cascade).
    if (($s['color'] ?? '') !== '') $sizeCss .= 'color:'.$col($s['color']).';';
    // flex_child: how THIS block behaves inside a flex parent — same as CSS.
    $fc = (array) ($s['flex_child'] ?? []);
    if (isset($fc['grow']))       $sizeCss .= 'flex-grow:'.$fc['grow'].';';
    if (isset($fc['shrink']))     $sizeCss .= 'flex-shrink:'.$fc['shrink'].';';
    if (($fc['basis'] ?? '') !== '')      $sizeCss .= 'flex-basis:'.$fc['basis'].';';
    if (($fc['align_self'] ?? '') !== '') $sizeCss .= 'align-self:'.$fc['align_self'].';';

    // Theme variables: "$primary" → var(--bk-primary) — the artboard defines
    // the custom properties, so canvas colours/sizes resolve live.
    $tv = fn (?string $v) => \App\Support\ThemeTokens::vars($v);
    $sizeCss = $tv($sizeCss); $css = $tv($css); $bgCss = $tv($bgCss); $dimCss = $tv($dimCss);

    $cols = $p['columns'] ?? null;
    $colBase = is_array($cols) ? ($cols['base'] ?? 1) : ((int) ($cols ?: 3));
    $colMd   = is_array($cols) ? ($cols['md'] ?? $colBase) : $colBase;
    $colLg   = is_array($cols) ? ($cols['lg'] ?? $colMd) : $colMd;

    $label = data_get($node, 'meta.label') ?: str_replace('_', ' ', $node['type']);
    $typeTag = match ($node['type']) {
        'grid', 'masonry' => $node['type']." · {$colLg}/{$colMd}/{$colBase} col",
        'header' => 'h'.substr($p['level'] ?? 'h2', 1),
        'media'  => $p['kind'] ?? 'image',
        default  => str_replace('_', ' ', $node['type']),
    };
@endphp

@if($isRoot)
    {{-- Invisible root: the artboard itself is the drop area. --}}
    <div data-bkw-list data-parent-id="{{ $node['id'] }}" class="bkw-root min-h-[600px] flex flex-col" style="gap:4px">
        @forelse($node['children'] as $child)
            @include('partials.blockkit-wysiwyg-node', ['node' => $child, 'selectedId' => $selectedId, 'catalogue' => $catalogue, 'depth' => $depth + 1])
        @empty
            <div class="bkw-empty flex-1 grid place-items-center pointer-events-none">
                <span class="font-mono text-[10px] tracking-[.08em] text-[#9aa8cf]">DRAG BLOCKS FROM THE LEFT — OR USE ＋ ADD BLOCK</span>
            </div>
        @endforelse
    </div>

@elseif($isSlot)
    {{-- Content section placeholder (layout canvas) / page drop area (page canvas). --}}
    <div @unless($readonly || ($node['_slot'] ?? false)) data-bkw-item draggable="true" data-id="{{ $node['id'] }}" @endunless
         class="bkw-node bkw-slot rounded-lg border-2 border-dashed border-indigo-300 bg-indigo-50/40 {{ $selectedId === $node['id'] ? 'bkw-selected' : '' }}"
         @unless($readonly) wire:click.stop="select('{{ $node['id'] }}')" @endunless>
        <span class="bkw-tag">content section</span>
        @if($node['_slot'] ?? false)
            <div data-bkw-list data-parent-id="{{ $node['id'] }}" class="min-h-[80px] flex flex-col p-2" style="gap:4px">
                @forelse($node['children'] as $child)
                    @include('partials.blockkit-wysiwyg-node', ['node' => $child, 'selectedId' => $selectedId, 'catalogue' => $catalogue, 'depth' => $depth + 1])
                @empty
                    <span class="m-auto py-5 font-mono text-[10px] tracking-[.08em] text-indigo-300 pointer-events-none">YOUR PAGE BLOCKS RENDER HERE</span>
                @endforelse
            </div>
        @else
            <p class="py-6 text-center font-mono text-[10px] tracking-[.08em] text-indigo-400 pointer-events-none">▣ CONTENT SECTION — each page's blocks render here</p>
        @endif
    </div>

@elseif($node['type'] === 'component_ref')
    {{-- ── LIVE component instance: renders the component's current tree.
         Component-owned parts are read-only (edit the component once → every
         copy updates); the slot area accepts this instance's own blocks. ── --}}
    <div @unless($readonly) data-bkw-item draggable="true" data-id="{{ $node['id'] }}" wire:click.stop="select('{{ $node['id'] }}')" @endunless
         class="bkw-node bkw-instance rounded {{ $selectedId === $node['id'] && ! $readonly ? 'bkw-selected' : '' }} {{ $readonly ? 'bkw-ro' : '' }}"
         @if($sizeCss) style="{{ $sizeCss }}" @endif>
        <span class="bkw-tag" style="background:#10b981">⚙ {{ $label }} · live</span>
        @unless($readonly)
            <span class="bkw-actions">
                <button type="button" data-bkw-dup="{{ $node['id'] }}" title="Duplicate instance">⧉</button>
                <button type="button" data-bkw-del="{{ $node['id'] }}" title="Remove this instance" class="bkw-del">✕</button>
            </span>
        @endunless
        @forelse($node['children'] as $child)
            @include('partials.blockkit-wysiwyg-node', ['node' => $child, 'selectedId' => $selectedId, 'catalogue' => $catalogue, 'depth' => $depth + 1])
        @empty
            <p class="py-4 text-center font-mono text-[10px] tracking-[.08em] text-emerald-600/70">EMPTY COMPONENT — design it in the Components tab</p>
        @endforelse
    </div>

@elseif($isCont)
    {{-- ── Container: transparent + dashed on the canvas; styles kept. ── --}}
    @php $isFill = (bool) ($node['_fill'] ?? false); @endphp
    <div @unless($readonly || $isFill) data-bkw-item draggable="true" data-id="{{ $node['id'] }}" wire:click.stop="select('{{ $node['id'] }}')" @endunless
         class="bkw-node bkw-container rounded {{ $selectedId === $node['id'] && ! $readonly && ! $isFill ? 'bkw-selected' : '' }} {{ $readonly ? 'bkw-ro' : '' }} {{ $isFill ? 'bkw-fill' : '' }}"
         style="{{ $node['type'] === 'panel' ? ($panelCss ?? '') : '' }}{{ $sizeCss }}{{ $dimCss }}{{ $bgCss }}{{ $zCss }}"{!! \App\Support\Fx::attrs($s) !!}
         @if($readonly) title="From the layout — edit it in the Layout view" @endif>
        <span class="bkw-tag">{{ $typeTag }}{{ $readonly ? ' · layout' : '' }}</span>
        @if(! $readonly && in_array($p['position'] ?? ($s['position'] ?? ''), ['absolute', 'fixed'], true))
            {{-- Backdrop-style container: an ALWAYS-VISIBLE handle — click selects,
                 dropping a palette/canvas block on it inserts INSIDE this container. --}}
            <span class="bkw-anchor" data-bkw-list data-parent-id="{{ $node['id'] }}"
                  wire:click.stop="select('{{ $node['id'] }}')"
                  title="{{ $label }} — click to select · drop blocks here to place them inside">
                ⚓ {{ $label }}
            </span>
        @endif
        @unless($readonly)
            <span class="bkw-actions">
                <button type="button" data-bkw-dup="{{ $node['id'] }}" title="Duplicate">⧉</button>
                <button type="button" data-bkw-del="{{ $node['id'] }}" title="Remove from canvas" class="bkw-del">✕</button>
            </span>
        @endunless

        @if($node['type'] === 'modal')<span class="bkw-chip">MODAL</span>@endif
        @if($node['type'] === 'lightbox')<span class="bkw-chip">LIGHTBOX</span>@endif
        @if($node['type'] === 'modal' && ($p['title'] ?? ''))<p class="px-3 pt-2 font-bold text-[15px] text-gray-900">{{ $p['title'] }}</p>@endif

        @if($node['type'] === 'navbar')
            <nav class="flex flex-wrap items-center px-2 py-1" style="gap:{{ $gap }}">
                @foreach((array) ($p['items'] ?? []) as $item)
                    @php [$lbl] = array_map('trim', explode('|', $item) + [1 => '#']); @endphp
                    <span class="px-3 py-1.5 rounded-lg text-[14px] font-medium text-[#3a3f4e] hover:bg-indigo-50 hover:text-indigo-600">{{ $lbl }}</span>
                @endforeach
                @if(empty($p['items']))<span class="px-3 py-1 text-xs text-gray-400 italic">navbar — add links in the inspector</span>@endif
            </nav>
        @endif

        {{-- Children --}}
        <div data-bkw-list data-parent-id="{{ $node['id'] }}"
             @if($isGridLike && $node['type'] !== 'masonry')
                 x-bind:style="'display:grid;gap:{{ $gap }};grid-template-columns:repeat(' + (device === 'mobile' ? {{ $colBase }} : (device === 'tablet' ? {{ $colMd }} : {{ $colLg }})) + ',1fr)'"
             @elseif($node['type'] === 'masonry')
                 x-bind:style="'column-count:' + (device === 'mobile' ? {{ $colBase }} : (device === 'tablet' ? {{ $colMd }} : {{ $colLg }})) + ';column-gap:{{ $gap }}'"
             @elseif(($node['type'] === 'flex' || ($p['display'] ?? '') === 'flex') && ($fdResp ?? false))
                 x-bind:style="'{{ $css }}flex-direction:' + (device === 'mobile' ? '{{ $fdBase }}' : (device === 'tablet' ? '{{ $fdMd }}' : '{{ $fdLg }}'))"
             @else
                 style="{{ $css }}"
             @endif
             class="min-h-[36px] {{ $isGridLike ? 'p-2' : '' }}">
            @forelse($node['children'] as $child)
                @include('partials.blockkit-wysiwyg-node', ['node' => $child, 'selectedId' => $selectedId, 'catalogue' => $catalogue, 'depth' => $depth + 1])
            @empty
                @unless($node['type'] === 'navbar')
                    <span class="w-full py-3 text-center font-mono text-[10px] tracking-[.08em] text-[#9aa8cf] pointer-events-none">DROP BLOCKS HERE</span>
                @endunless
            @endforelse
        </div>
    </div>

@else
    {{-- ── Leaf: render the real element. ── --}}
    @php $propOf = $node['_prop'] ?? null; $refOf = $node['_ref'] ?? null; @endphp
    @if($propOf !== null && $refOf)
        {{-- Prop content inside a live instance: CLICK IT to edit the value —
             selecting jumps straight to the instance's prop fields. --}}
        <div wire:click.stop="select('{{ $refOf }}')"
             class="bkw-node bkw-prop-live {{ $selectedId === $refOf ? 'bkw-selected' : '' }}"
             @if($sizeCss) style="{{ $sizeCss }}" @endif
             title="Prop “{{ $propOf }}” — click to edit its value for this instance">
            <span class="bkw-tag" style="background:#d97706">◇ {{ $propOf }} · click to edit</span>
    @else
    <div @unless($readonly) data-bkw-item draggable="true" data-id="{{ $node['id'] }}" wire:click.stop="select('{{ $node['id'] }}')" @endunless
         class="bkw-node {{ $selectedId === $node['id'] && ! $readonly ? 'bkw-selected' : '' }} {{ $readonly ? 'bkw-ro' : '' }}"
         @if($sizeCss) style="{{ $sizeCss }}" @endif{!! \App\Support\Fx::attrs($s) !!}
         @if($readonly) title="From the layout — edit it in the Layout view" @endif>
        <span class="bkw-tag">{{ $typeTag }}{{ $readonly ? ' · layout' : '' }}</span>
    @endif
        @unless($readonly || $propOf)
            <span class="bkw-actions">
                <button type="button" data-bkw-dup="{{ $node['id'] }}" title="Duplicate">⧉</button>
                <button type="button" data-bkw-del="{{ $node['id'] }}" title="Remove from canvas" class="bkw-del">✕</button>
            </span>
        @endunless

        @switch($node['type'])
            @case('header')
                @php $lvl = max(1, min(6, (int) substr($p['level'] ?? 'h2', 1) ?: 2));
                     // renderer parity: same clamp() scales + weights as .bk-h1…
                     $hs = [1 => 'clamp(2rem, 5vw, 3.2rem)', 2 => 'clamp(1.6rem, 3.5vw, 2.3rem)', 3 => '1.25rem', 4 => '1rem', 5 => '.9rem', 6 => '.8rem'];
                     $hw = [1 => 900, 2 => 800, 3 => 700, 4 => 700, 5 => 700, 6 => 700]; @endphp
                <div @unless($readonly) data-bkw-text="{{ $node['id'] }}" @endunless style="font-size:{{ $tv($p['size'] ?? '') ?: $hs[$lvl] }};font-weight:{{ $hw[$lvl] }};line-height:{{ $lvl === 1 ? '1.1' : ($lvl === 2 ? '1.2' : '1.3') }};letter-spacing:-0.015em;{{ ($p['color'] ?? '') !== '' ? 'color:'.$tv($p['color']).';' : '' }}{{ ($p['font'] ?? '') !== '' ? 'font-family:'.$tv($p['font']).';' : '' }}text-align:{{ $p['align'] ?? 'left' }};{{ ($p['text_gradient'] ?? '') !== '' ? 'background-image:'.$tv($p['text_gradient']).';-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent;' : '' }}">
                    {!! \App\Support\RichText::clean($p['content'] ?? 'Heading text') !!}
                </div>
                @break

            @case('content')
                <p @unless($readonly) data-bkw-text="{{ $node['id'] }}" @endunless style="{{ ($p['color'] ?? '') !== '' ? 'color:'.$tv($p['color']).';' : '' }}font-size:{{ $tv($p['size'] ?? '15px') }};line-height:1.62;text-align:{{ $p['align'] ?? 'left' }};{{ ($p['text_gradient'] ?? '') !== '' ? 'background-image:'.$tv($p['text_gradient']).';-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent;' : '' }}">
                    {!! ($p['content'] ?? '') !== '' ? \App\Support\RichText::clean($p['content']) : 'Write supporting copy here…' !!}
                </p>
                @break

            @case('button')
                @php // renderer parity: .bk-btn--{style} + .bk-btn--{size}
                    $bStyle = $p['style'] ?? 'primary';
                    $bSize = ['sm' => 'font-size:.8rem;padding:.45rem 1rem;', 'md' => 'font-size:.92rem;padding:.7rem 1.5rem;', 'lg' => 'font-size:1.05rem;padding:.9rem 2rem;'][$p['size'] ?? 'md'] ?? 'font-size:.92rem;padding:.7rem 1.5rem;';
                    $bLook = match ($bStyle) {
                        'secondary' => 'background:transparent;color:var(--accent, #6366f1);border:2px solid var(--accent, #6366f1);',
                        'ghost'     => 'background:transparent;color:inherit;text-decoration:underline;',
                        default      => 'background:var(--accent, #6366f1);color:#fff;',
                    };
                @endphp
                <button type="button" class="inline-flex items-center justify-center font-semibold rounded-[9px] pointer-events-none"
                        style="{{ $bLook }}{{ $bSize }}border-radius:9px;">
                    {{ $p['label'] ?? 'Button' }}
                </button>
                @break

            @case('media')
                @php
                    $mkind = $p['kind'] ?? 'image';
                    // Media-library refs (@media/…) resolve to the stored file — the
                    // canvas shows the REAL image, same as the rendered site.
                    $src = $p['src'] ?? null;
                    if (! $src && ! empty($p['asset_id'])) {
                        $src = \App\Models\Media::resolveRef($site->id, (string) $p['asset_id']) ?: null;
                    }
                    if ($src && ! preg_match('~^https?://~', $src)) {
                        $src = url($src);
                    }
                    $isUrl = (bool) $src;
                @endphp
                @if($mkind === 'video' || $mkind === 'audio')
                    <div class="relative rounded-xl overflow-hidden bg-[#0d0f16]" style="aspect-ratio:16/9">
                        <div class="absolute inset-0 grid place-items-center text-[#5f6c92]" style="background:linear-gradient(135deg,#1a2340,#0d1524)">
                            <svg class="w-11 h-11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9.5v5l4.5-2.5z" fill="currentColor"/></svg>
                        </div>
                        <span class="absolute inset-0 m-auto w-14 h-14 rounded-full bg-white/95 shadow-xl grid place-items-center">
                            <svg class="w-5 h-5 ml-0.5" viewBox="0 0 24 24" fill="#2f6bff"><path d="M8 5v14l11-7z"/></svg>
                        </span>
                        @if($src)<span class="absolute bottom-1.5 left-2 font-mono text-[9px] text-white/70 truncate max-w-[90%]">{{ $src }}</span>@endif
                    </div>
                @elseif($isUrl)
                    @if(($p['ratio'] ?? '') === 'original')
                        {{-- The asset's own intrinsic size & aspect ratio, capped at the parent frame. --}}
                        <img src="{{ $src }}" alt="{{ $p['alt'] ?? '' }}" class="block rounded-[10px]" style="width:auto;height:auto;max-width:100%">
                    @else
                        <img src="{{ $src }}" alt="{{ $p['alt'] ?? '' }}" class="block w-full rounded-[10px] object-cover" style="{{ ($p['ratio'] ?? '') && ($p['ratio'] ?? '') !== 'auto' ? 'aspect-ratio:'.str_replace(':', '/', $p['ratio']) : 'max-height:260px' }}">
                    @endif
                @else
                    <div class="w-full rounded-[10px] bg-[#eef1f7] grid place-items-center text-[#a9b0c4]" style="{{ ($p['ratio'] ?? '') && ! in_array($p['ratio'], ['auto', 'original'], true) ? 'aspect-ratio:'.str_replace(':', '/', $p['ratio']) : 'height:180px' }}">
                        <div class="text-center px-3">
                            <svg class="w-8 h-8 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M4 18l5-5 3 3 4-4 4 4"/></svg>
                            @if($p['asset_id'] ?? $p['image_brief'] ?? false)<p class="mt-1 font-mono text-[9px] truncate max-w-[200px]">{{ $p['asset_id'] ?? $p['image_brief'] }}</p>@endif
                        </div>
                    </div>
                @endif
                @break

            @case('list')
                <ul class="flex flex-col" style="gap:8px;{{ ($p['ordered'] ?? false) ? 'list-style:decimal inside;' : '' }}">
                    @forelse((array) ($p['items'] ?? []) as $item)
                        <li class="flex items-center gap-2.5 text-[14.5px] text-[#2c3040]">
                            @unless($p['ordered'] ?? false)<span class="w-[7px] h-[7px] rounded-[2px] bg-[#2f6bff] shrink-0"></span>@endunless
                            {{ $item }}
                        </li>
                    @empty
                        <li class="text-xs text-gray-400 italic">list — add items in the inspector</li>
                    @endforelse
                </ul>
                @break

            @case('tile')
                <div class="rounded-[14px] bg-[#f6f8fd] p-4">
                    <p class="font-bold text-[15px] text-gray-900">{{ $p['label'] ?? 'Tile' }}</p>
                    @if($p['sublabel'] ?? '')<p class="text-[12px] text-gray-500 mt-0.5">{{ $p['sublabel'] }}</p>@endif
                </div>
                @break

            @case('input')
                <div><label class="block text-[12.5px] font-semibold text-[#3a3f4e] mb-1">{{ $p['label'] ?? 'Field' }}@if($p['required'] ?? false)<em class="text-rose-500"> *</em>@endif</label>
                <input type="text" placeholder="{{ $p['placeholder'] ?? $p['label'] ?? '' }}" class="w-full border border-[#d6dae6] rounded-lg px-3 py-2.5 text-[14px] bg-white text-gray-900 pointer-events-none" tabindex="-1"></div>
                @break

            @case('textarea')
                <div><label class="block text-[12.5px] font-semibold text-[#3a3f4e] mb-1">{{ $p['label'] ?? 'Text area' }}</label>
                <textarea rows="{{ $p['rows'] ?? 3 }}" class="w-full border border-[#d6dae6] rounded-lg px-3 py-2.5 text-[14px] bg-white pointer-events-none" tabindex="-1"></textarea></div>
                @break

            @case('select')
                <div><label class="block text-[12.5px] font-semibold text-[#3a3f4e] mb-1">{{ $p['label'] ?? 'Select' }}</label>
                <select class="w-full border border-[#d6dae6] rounded-lg px-3 py-2.5 text-[14px] bg-white pointer-events-none" tabindex="-1">
                    @foreach((array) ($p['options'] ?? []) as $o)<option>{{ $o }}</option>@endforeach
                </select></div>
                @break

            @case('checkbox')
                <label class="flex items-center gap-2 text-[13.5px] text-[#3a3f4e] pointer-events-none"><input type="checkbox" class="rounded border-gray-300" tabindex="-1"> {{ $p['label'] ?? 'Checkbox' }}</label>
                @break

            @case('divider')
                <hr class="border-t border-[#e3e7f2] my-2">
                @break

            @case('booking')
                {{-- Interactive widget — runs live on the rendered site. --}}
                <div class="rounded-xl border-2 border-dashed border-indigo-300 bg-indigo-50/50 px-4 py-6 text-center">
                    <p class="text-2xl mb-1">📅</p>
                    <p class="text-sm font-bold text-indigo-700">{{ $p['headline'] ?? 'Booking widget' }}</p>
                    <p class="text-[11px] text-indigo-400 mt-0.5">
                        {{ ($p['service'] ?? '') !== '' ? 'Service: '.$p['service'] : 'Customer picks a service' }}
                        · appointments / stays / trips — live in Preview &amp; on the site
                    </p>
                </div>
                @break

            @case('estimator')
                {{-- Interactive widget — runs live on the rendered site. --}}
                <div class="rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/50 px-4 py-6 text-center">
                    <p class="text-2xl mb-1">🧮</p>
                    <p class="text-sm font-bold text-emerald-700">{{ $p['headline'] ?? 'Cost estimator' }}</p>
                    <p class="text-[11px] text-emerald-500 mt-0.5">
                        {{ ($p['trade'] ?? '') !== '' ? 'Trade: '.$p['trade'] : 'Visitor picks a trade' }}
                        · instant cost + completion time — live in Preview &amp; on the site
                    </p>
                </div>
                @break

            @case('prop')
                {{-- ◇ Prop placeholder: the passed-in value appears exactly here. --}}
                <div class="rounded-lg border-2 border-dashed border-amber-400 bg-amber-50/60 px-3 py-2.5">
                    <p class="font-mono text-[10px] font-bold tracking-[.08em] text-amber-600 uppercase">
                        ◇ prop: {{ $p['name'] ?? '(unnamed — set a name in the inspector)' }} · {{ $p['kind'] ?? 'heading' }}
                    </p>
                    @if(($p['default'] ?? '') !== '')
                        <p class="text-[12px] text-amber-700/80 mt-0.5 truncate">default: {{ $p['default'] }}</p>
                    @endif
                </div>
                @break

            @default
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-xs text-gray-500 font-mono">{{ $node['type'] }}</div>
        @endswitch
    </div>
@endif
