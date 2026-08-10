<?php

namespace App\Services;

use App\Support\Css;
use App\Support\Fx;
use App\Support\RichText;
use App\Support\ThemeTokens;

/**
 * Turns a composed block tree into one self-contained HTML page:
 * semantic tags, per-block classes, responsive grid breakpoints.
 * Mirrors the WYSIWYG canvas (and the Nuxt renderer's structure) so
 * what you export is what you built.
 */
class BlockHtmlExporter
{
    /** #rrggbb + opacity % → rgba(); rgba()/named colours pass through. */
    private static function alphaColor(string $color, int $pct): string
    {
        $pct = max(0, min(100, $pct));
        if (preg_match('/^#([0-9a-f]{6})$/i', trim($color), $m)) {
            [$r, $g, $b] = array_map('hexdec', str_split($m[1], 2));

            return "rgba({$r},{$g},{$b},".round($pct / 100, 2).')';
        }

        // $theme-variables / named colours: opacity still applies via color-mix.
        return "color-mix(in srgb, {$color} {$pct}%, transparent)";
    }

    private static array $css = [];

    private static int $i = 0;

    /**
     * The full CSS background model, shared by every layout block: colour,
     * then the image stack overlay tint → gradient → background-image, plus
     * the longhands (size/position/repeat/attachment/clip/origin/blend-mode).
     */
    private static function backgroundRules(array $p): string
    {
        $rules = '';
        // Precedence: image/gradient win over the colour (overlay is a tint, not a base).
        $hasBgLayer = (($p['gradient'] ?? '') !== '') || (($p['bg_image'] ?? '') !== '');
        if (($bg = $p['background'] ?? null) && $bg !== 'transparent' && ! $hasBgLayer) {
            $rules .= "background-color:{$bg};";
        }
        $bgLayers = [];
        if (($p['overlay'] ?? '') !== '') {
            $ov = self::alphaColor($p['overlay'], (int) ($p['overlay_opacity'] ?? 50));
            $bgLayers[] = "linear-gradient({$ov},{$ov})";
        }
        // Gradient overlay: a second tint layer with its own opacity.
        if (($p['overlay_gradient'] ?? '') !== '') {
            $bgLayers[] = Css::fadeGradient($p['overlay_gradient'], (int) ($p['overlay_gradient_opacity'] ?? 50));
        }
        if (($p['gradient'] ?? '') !== '') {
            $bgLayers[] = $p['gradient'];
        }
        // background-image (media ref resolved upstream, or plain URL) — bottom layer.
        if (($p['bg_image'] ?? '') !== '') {
            $bgLayers[] = "url('".str_replace(["'", '"', '\\'], '', $p['bg_image'])."')";
        }
        if ($bgLayers) {
            $rules .= 'background-image:'.implode(',', $bgLayers).';';
        }
        foreach (['bg_size' => 'background-size', 'bg_position' => 'background-position', 'bg_repeat' => 'background-repeat',
            'bg_attachment' => 'background-attachment', 'bg_clip' => 'background-clip', 'bg_origin' => 'background-origin',
            'bg_blend' => 'background-blend-mode'] as $bk => $bprop) {
            if (($p[$bk] ?? '') !== '') {
                $rules .= "{$bprop}:{$p[$bk]};";
            }
        }
        if (($p['bg_clip'] ?? '') === 'text') {
            $rules .= '-webkit-background-clip:text;color:transparent;-webkit-text-fill-color:transparent;';
        }
        // CSS filters: blur (px) + percentage filters — emitted only when set.
        $filters = '';
        if (($p['blur'] ?? '') !== '') {
            $filters .= 'blur('.max(0, (int) $p['blur']).'px) ';
        }
        foreach (['brightness', 'contrast', 'saturate', 'grayscale'] as $fk) {
            if (($p[$fk] ?? '') !== '') {
                $filters .= "{$fk}(".max(0, (int) $p[$fk]).'%) ';
            }
        }
        if ($filters !== '') {
            $rules .= 'filter:'.trim($filters).';';
        }
        // backdrop-filter: blur — frosts what shows THROUGH the block.
        if (($p['backdrop_blur'] ?? '') !== '') {
            $bb = 'blur('.max(0, (int) $p['backdrop_blur']).'px)';
            $rules .= "backdrop-filter:{$bb};-webkit-backdrop-filter:{$bb};";
        }

        return $rules;
    }

    private static array $scripts = [];

    public static function render(array $tree, string $title = 'Exported page', array $themeVariables = [], string $pageScript = ''): string
    {
        self::$css = [];
        self::$i = 0;
        self::$scripts = [];
        if (trim($pageScript) !== '') {
            self::$scripts[] = '(function(){try{'.$pageScript.'}catch(e){console.error("page script",e)}})();';
        }
        $body = self::node($tree, true);
        $rules = implode("\n", self::$css);
        // Theme variables: declare as :root custom properties and resolve
        // every "$name" reference to var(--bk-name) — same as the renderer.
        if ($vars = ThemeTokens::rootCss($themeVariables)) {
            $rules = ":root{{$vars}}\n".$rules;
        }
        $rules = ThemeTokens::vars($rules);
        $body = ThemeTokens::vars($body);
        $safe = e($title);
        // Effects engine + user scripts (block meta.script / page custom_js).
        $fxCss = Fx::css();
        $fxJs = Fx::js();
        $userScripts = implode("\n", self::$scripts);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$safe}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#fff;color:#1a1c22;line-height:1.5;position:relative}
img{max-width:100%;display:block}
.bk-video{position:relative;border-radius:12px;overflow:hidden;background:#0d0f16;aspect-ratio:16/9;display:grid;place-items:center}
.bk-video iframe,.bk-video video{position:absolute;inset:0;width:100%;height:100%;border:0}
nav.bk-nav a{color:#3a3f4e;text-decoration:none;font-weight:500;padding:7px 12px;border-radius:7px}
nav.bk-nav a:hover{background:#eef2ff;color:#2f6bff}
form label{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:600;margin-bottom:10px}
form input,form textarea,form select{border:1px solid #d6dae6;border-radius:8px;padding:10px 12px;font-size:14px;font-family:inherit}
button.bk-btn{background:#2f6bff;color:#fff;border:0;border-radius:9px;padding:11px 20px;font-weight:600;cursor:pointer;font-size:14px}
ul.bk-list{list-style:none}ul.bk-list li{padding:4px 0;display:flex;gap:10px;align-items:center}
ul.bk-list li::before{content:"";width:7px;height:7px;border-radius:2px;background:#2f6bff;flex:none}
{$rules}
{$fxCss}
</style>
</head>
<body>
{$body}
<script>
// Real browser behavior for exported blocks — no framework, just the platform.
document.addEventListener('click', (e) => {
  const t = e.target.closest('[data-open]');
  if (!t) return;
  const dlg = document.getElementById(t.dataset.open) || document.querySelector('dialog');
  if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
});
document.addEventListener('submit', (e) => {
  const f = e.target.closest('form[data-bk-form]');
  if (!f) return;
  e.preventDefault(); // no endpoint configured — confirm inline
  let ok = f.querySelector('.bk-form-ok');
  if (!ok) { ok = document.createElement('p'); ok.className = 'bk-form-ok'; ok.style.cssText = 'color:#15803d;font-weight:600'; f.appendChild(ok); }
  ok.textContent = f.dataset.success || 'Thanks!';
  f.reset();
});
</script>
<script>{$fxJs}</script>
<script>{$userScripts}</script>
</body>
</html>
HTML;
    }

    private static function node(array $n, bool $isRoot = false): string
    {
        $type = $n['type'];
        $p = $n['props'] ?? [];
        $cls = 'b'.(self::$i++);
        $rules = '';
        $tag = 'div';
        $fx = Fx::attrs($n['style'] ?? []); // data-fx-* on every surface
        $inner = '';

        $padMap = ['none' => '0', 'xs' => '6px', 'sm' => '12px', 'md' => '20px', 'lg' => '32px', 'xl' => '48px'];
        $gapMap = ['none' => '0', 'xs' => '4px', 'sm' => '8px', 'md' => '16px', 'lg' => '24px', 'xl' => '36px'];
        $pad = $padMap[$p['padding'] ?? ''] ?? '16px';
        $gap = $gapMap[$p['gap'] ?? ''] ?? '16px';
        $kids = fn () => implode("\n", array_map(fn ($c) => self::node($c), $n['children'] ?? []));
        // Shared style keys land on EVERY element — appended last so they win.
        $styleCss = self::styleRules($n['style'] ?? [], $padMap);

        switch ($type) {
            case 'modal':
                // Real HTML: a native <dialog>, opened by button actions (open_modal).
                $rules .= "display:block;padding:{$pad};border:0;border-radius:14px;max-width:min(560px,92vw);box-shadow:0 24px 70px rgba(10,15,40,.3);";
                $rules .= self::backgroundRules($p);
                $inner .= '<form method="dialog" style="text-align:right"><button aria-label="Close" style="border:0;background:none;font-size:18px;cursor:pointer">✕</button></form>';
                if (! empty($p['title'])) {
                    $inner .= '<h3>'.e($p['title']).'</h3>';
                }
                $inner .= $kids();

                return '<dialog id="'.e($n['id'] ?? $cls).'" class="'.$cls.'"'.$fx.'>'.$inner.'</dialog>'.self::push($cls, $rules.$styleCss);

            case 'content_slot':
            case 'container': case 'panel': case 'flex': case 'card': case 'lightbox': case 'header_group': case 'slot':
                // CSS-faithful layout — a flex block maps 1:1 onto real flexbox.
                if ($type === 'flex') {
                    // Mobile-first responsive direction: string, or {base, md, lg} → media queries.
                    $d = $p['direction'] ?? 'row';
                    if (is_array($d)) {
                        $dBase = $d['base'] ?? 'column';
                        $dMd = $d['md'] ?? $dBase;
                        $dLg = $d['lg'] ?? $dMd;
                        $rules .= "display:flex;flex-direction:{$dBase};";
                        if ($dMd !== $dBase) {
                            self::$css[] = "@media(min-width:820px){.{$cls}{flex-direction:{$dMd}}}";
                        }
                        if ($dLg !== $dMd) {
                            self::$css[] = "@media(min-width:1100px){.{$cls}{flex-direction:{$dLg}}}";
                        }
                    } else {
                        $rules .= 'display:flex;flex-direction:'.$d.';';
                    }
                    $rules .= 'flex-wrap:'.($p['flex_wrap'] ?? 'nowrap').';';
                    foreach (['justify_content' => 'justify-content', 'align_items' => 'align-items', 'align_content' => 'align-content'] as $prop => $cssProp) {
                        if (($p[$prop] ?? '') !== '') {
                            $rules .= "{$cssProp}:{$p[$prop]};";
                        }
                    }
                    $rules .= "gap:{$gap};padding:{$pad};";
                } elseif (($p['display'] ?? '') === 'flex') {
                    // container display:flex — same properties as the Flex block.
                    $d = $p['direction'] ?? 'row';
                    if (is_array($d)) {
                        $dBase = $d['base'] ?? 'column';
                        $dMd = $d['md'] ?? $dBase;
                        $dLg = $d['lg'] ?? $dMd;
                        $rules .= "display:flex;flex-direction:{$dBase};";
                        if ($dMd !== $dBase) {
                            self::$css[] = "@media(min-width:820px){.{$cls}{flex-direction:{$dMd}}}";
                        }
                        if ($dLg !== $dMd) {
                            self::$css[] = "@media(min-width:1100px){.{$cls}{flex-direction:{$dLg}}}";
                        }
                    } else {
                        $rules .= 'display:flex;flex-direction:'.$d.';';
                    }
                    $rules .= 'flex-wrap:'.($p['flex_wrap'] ?? 'nowrap').';';
                    foreach (['justify_content' => 'justify-content', 'align_items' => 'align-items', 'align_content' => 'align-content'] as $prop => $cssProp) {
                        if (($p[$prop] ?? '') !== '') {
                            $rules .= "{$cssProp}:{$p[$prop]};";
                        }
                    }
                    $rules .= "gap:{$gap};padding:{$pad};";
                } elseif (($p['display'] ?? '') === 'grid') {
                    // container display:grid — same responsive columns as the Grid block.
                    $cc = $p['columns'] ?? ['base' => 1];
                    $cmap = is_array($cc) ? $cc : ['base' => (int) $cc];
                    $cb = $cmap['base'] ?? 1;
                    $cm = $cmap['md'] ?? $cb;
                    $cl = $cmap['lg'] ?? $cm;
                    $rules .= "display:grid;grid-template-columns:repeat({$cb},1fr);gap:{$gap};padding:{$pad};";
                    if ($cm !== $cb) {
                        self::$css[] = "@media(min-width:820px){.{$cls}{grid-template-columns:repeat({$cm},1fr)}}";
                    }
                    if ($cl !== $cm) {
                        self::$css[] = "@media(min-width:1100px){.{$cls}{grid-template-columns:repeat({$cl},1fr)}}";
                    }
                } else {
                    $rules .= "display:flex;flex-direction:column;gap:{$gap};padding:{$pad};";
                }
                $rules .= self::backgroundRules($p);
                if (($p['z_index'] ?? '') !== '') {
                    $rules .= 'z-index:'.((int) $p['z_index']).';';
                    if (($p['position'] ?? '') === '') {
                        $rules .= 'position:relative;';
                    }
                }
                if (($p['display'] ?? '') !== '' && $p['display'] !== 'flex') {
                    $rules .= "display:{$p['display']};";
                }
                if (($p['position'] ?? '') !== '') {
                    $rules .= "position:{$p['position']};";
                }
                foreach ((array) ($p['inset'] ?? []) as $side => $v) {
                    if ($v !== '' && $v !== null && in_array($side, ['top', 'right', 'bottom', 'left'], true)) {
                        $rules .= "{$side}:{$v};";
                    }
                }
                if (($p['overflow'] ?? '') !== '') {
                    $rules .= "overflow:{$p['overflow']};";
                }
                if (($p['opacity'] ?? '') !== '') {
                    $rules .= 'opacity:'.max(0, min(100, (int) $p['opacity'])) / 100 .';';
                }
                if (($p['width'] ?? '') !== '') {
                    $rules .= "width:{$p['width']};";
                }
                if (($p['height'] ?? '') !== '') {
                    $rules .= "height:{$p['height']};";
                }
                // Per-side margin prop — each side a unit, token, $variable or AUTO (centering).
                if (is_array($p['margin'] ?? null)) {
                    $rules .= 'margin:'.implode(' ', array_map(fn ($sd) => $padMap[$p['margin'][$sd] ?? ''] ?? (($p['margin'][$sd] ?? '') !== '' ? $p['margin'][$sd] : '0'), ['top', 'right', 'bottom', 'left'])).';';
                }
                if (($p['max_width'] ?? '') === 'xl') {
                    $rules .= 'max-width:1120px;margin-left:auto;margin-right:auto;';
                }
                if ($type === 'panel' || $type === 'card') {
                    $rules .= 'border-radius:12px;';
                }
                if ($type === 'card') {
                    $rules .= 'box-shadow:0 6px 24px rgba(20,30,60,.08);';
                }
                if (! empty($p['title'])) {
                    $inner .= '<h3>'.e($p['title']).'</h3>';
                }
                $inner .= $kids();
                break;

            case 'header':
                $tag = 'h'.max(1, min(6, (int) substr($p['level'] ?? 'h2', 1) ?: 2));
                $inner = RichText::clean($p['content'] ?? ''); // inline rich text (allow-listed)
                if (($p['color'] ?? '') !== '') {
                    $rules .= "color:{$p['color']};";
                } // unset → inherits from parent
                $rules .= 'text-align:'.($p['align'] ?? 'left').';line-height:1.15;';
                if (($p['text_gradient'] ?? '') !== '') {
                    $rules .= "background-image:{$p['text_gradient']};-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent;";
                }
                break;

            case 'content':
                $tag = 'p';
                $inner = RichText::clean($p['content'] ?? ''); // inline rich text (allow-listed)
                if (($p['color'] ?? '') !== '') {
                    $rules .= "color:{$p['color']};";
                } // unset → inherits
                $rules .= 'font-size:'.($p['size'] ?? '15px').';line-height:1.62;text-align:'.($p['align'] ?? 'left').';';
                if (($p['text_gradient'] ?? '') !== '') {
                    $rules .= "background-image:{$p['text_gradient']};-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent;";
                }
                break;

            case 'button':
                $act = $p['action'] ?? [];
                $actType = $act['type'] ?? 'link';
                $url = $act['url'] ?? $act['target'] ?? '#';
                $label = e($p['label'] ?? 'Button');
                $cls .= ' bk-btn';
                $rules .= 'display:inline-flex;text-decoration:none;align-self:flex-start;';
                // Real behavior per action type: link → <a>; submit → <button type=submit>;
                // open_modal / open_lightbox → JS trigger for the target <dialog>.
                if ($actType === 'link') {
                    return "<a class=\"{$cls}\"{$fx} href=\"".e($url).'">'.$label.'</a>'.self::push($cls, $rules.$styleCss);
                }
                if (in_array($actType, ['open_modal', 'open_lightbox'], true)) {
                    return "<button type=\"button\" class=\"{$cls}\"{$fx} data-open=\"".e($act['target'] ?? '')."\">{$label}</button>".self::push($cls, $rules.$styleCss);
                }

                return "<button type=\"submit\" class=\"{$cls}\"{$fx}>{$label}</button>".self::push($cls, $rules.$styleCss);

            case 'navbar':
                $tag = 'nav';
                $cls .= ' bk-nav';
                $rules .= "display:flex;flex-wrap:wrap;align-items:center;gap:{$gap};padding:{$pad};";
                foreach ((array) ($p['items'] ?? []) as $item) {
                    [$lbl, $url] = array_map('trim', explode('|', $item) + [1 => '#']);
                    $inner .= '<a href="'.e($url).'">'.e($lbl).'</a>';
                }
                break;

            case 'media':
                $kind = $p['kind'] ?? 'image';
                $src = $p['src'] ?? $p['asset_id'] ?? '';
                if ($kind === 'video') {
                    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)~', $src, $m)) {
                        $inner = '<div class="bk-video"><iframe src="https://www.youtube.com/embed/'.e($m[1]).'" allowfullscreen loading="lazy"></iframe></div>';
                    } else {
                        $inner = '<div class="bk-video"><video src="'.e($src).'" controls></video></div>';
                    }
                } else {
                    $r = $p['ratio'] ?? '';
                    // 'original' = intrinsic size & aspect ratio, never cropped, capped at the parent.
                    $imgCss = $r === 'original'
                        ? 'width:auto;height:auto;max-width:100%;'
                        : (($r !== '' && $r !== 'auto') ? 'aspect-ratio:'.str_replace(':', '/', $r).';object-fit:cover;width:100%;' : '');
                    $inner = '<img src="'.e($src).'" alt="'.e($p['alt'] ?? '').'" style="border-radius:10px;'.$imgCss.'" loading="lazy">';
                }
                if (($p['ratio'] ?? '') !== 'original') {
                    $rules .= 'width:100%;';
                }
                break;

            case 'grid': case 'masonry':
                $c = $p['columns'] ?? ['base' => 1, 'md' => 2, 'lg' => 3];
                $base = is_array($c) ? ($c['base'] ?? 1) : (int) $c;
                $md = is_array($c) ? ($c['md'] ?? $base) : $base;
                $lg = is_array($c) ? ($c['lg'] ?? $md) : $md;
                if ($type === 'grid') {
                    $rules .= "display:grid;grid-template-columns:repeat({$lg},1fr);gap:{$gap};padding:{$pad};";
                    self::$css[] = "@media(max-width:900px){.{$cls}{grid-template-columns:repeat({$md},1fr)}}";
                    self::$css[] = "@media(max-width:600px){.{$cls}{grid-template-columns:repeat({$base},1fr)}}";
                } else {
                    $rules .= "column-count:{$lg};column-gap:{$gap};padding:{$pad};";
                    self::$css[] = "@media(max-width:900px){.{$cls}{column-count:{$md}}}";
                    self::$css[] = "@media(max-width:600px){.{$cls}{column-count:{$base}}}";
                }
                $rules .= self::backgroundRules($p);
                $inner .= $kids();
                break;

            case 'form':
                $tag = 'form';
                $rules .= "display:flex;flex-direction:column;gap:{$gap};padding:{$pad};";
                $inner .= $kids();
                $target = $p['action_target'] ?? '';
                $msg = e($p['success_message'] ?? 'Thanks — we got your message.');
                $attrs = $target !== ''
                    ? ' method="'.e(strtolower($p['method'] ?? 'post')).'" action="'.e($target).'"'
                    : ' data-bk-form data-success="'.$msg.'"'; // no endpoint yet → friendly inline confirm

                return "<form class=\"{$cls}\"{$fx}{$attrs}>{$inner}</form>".self::push($cls, $rules.$styleCss);

            case 'input':
                $inner = '<label>'.e($p['label'] ?? 'Field').'<input type="'.e($p['field_type'] ?? 'text').'" name="'.e($p['name'] ?? '').'" placeholder="'.e($p['placeholder'] ?? '').'"'.(($p['required'] ?? false) ? ' required' : '').'></label>';
                break;
            case 'textarea':
                $inner = '<label>'.e($p['label'] ?? 'Message').'<textarea name="'.e($p['name'] ?? '').'" rows="'.(int) ($p['rows'] ?? 3).'"></textarea></label>';
                break;
            case 'select':
                $opts = implode('', array_map(fn ($o) => '<option>'.e($o).'</option>', (array) ($p['options'] ?? [])));
                $inner = '<label>'.e($p['label'] ?? 'Select').'<select name="'.e($p['name'] ?? '').'">'.$opts.'</select></label>';
                break;
            case 'checkbox':
                $inner = '<label style="flex-direction:row;align-items:center"><input type="checkbox" name="'.e($p['name'] ?? '').'"> '.e($p['label'] ?? '').'</label>';
                break;

            case 'list':
                $tag = ($p['ordered'] ?? false) ? 'ol' : 'ul';
                if ($tag === 'ul') {
                    $cls .= ' bk-list';
                }
                $inner = implode('', array_map(fn ($i) => '<li>'.e($i).'</li>', (array) ($p['items'] ?? [])));
                break;

            case 'tile':
                $rules .= 'background:#f6f8fd;border-radius:14px;padding:18px;';
                $inner = '<strong>'.e($p['label'] ?? '').'</strong>'.(($p['sublabel'] ?? '') ? '<div style="font-size:13px;color:#6b7185">'.e($p['sublabel']).'</div>' : '');
                break;

            case 'divider':
                return '<hr style="border:0;border-top:1px solid #e3e7f2;margin:12px 0">';

            default:
                $inner .= $kids();
                $rules .= "padding:{$pad};";
        }

        if ($isRoot) {
            $rules .= 'min-height:100vh;';
        }

        // Per-block custom script hook (id target for the script IIFE).
        $idAttr = '';
        if (($n['meta']['script'] ?? '') !== '') {
            $idAttr = ' id="'.e($n['id'] ?? $cls).'"';
            self::$scripts[] = '(function(el){try{'.$n['meta']['script'].'}catch(e){console.error("block script",e)}})(document.getElementById('.json_encode($n['id'] ?? $cls).'));';
        }

        return "<{$tag} class=\"{$cls}\"{$idAttr}{$fx}>{$inner}</{$tag}>".self::push($cls, $rules.$styleCss);
    }

    /** Shared per-block style keys → real CSS, identical to the canvas & renderer. */
    private static function styleRules(array $s, array $padMap): string
    {
        $radMap = ['none' => '0', 'sm' => '6px', 'md' => '12px', 'lg' => '20px', 'full' => '9999px'];
        $sides = function ($v) use ($padMap) {
            if (is_string($v)) {
                return $padMap[$v] ?? $v;
            }
            if (! is_array($v)) {
                return null;
            }

            return implode(' ', array_map(fn ($side) => $padMap[$v[$side] ?? ''] ?? ($v[$side] ?? '0'), ['top', 'right', 'bottom', 'left']));
        };
        $css = '';
        if (($s['width'] ?? '') !== '') {
            $css .= "width:{$s['width']};";
        }
        if (($s['height'] ?? '') !== '') {
            $css .= "height:{$s['height']};";
        }
        foreach (['min_width' => 'min-width', 'max_width' => 'max-width', 'min_height' => 'min-height', 'max_height' => 'max-height'] as $sk => $cssProp) {
            if (($s[$sk] ?? '') !== '') {
                $css .= "{$cssProp}:{$s[$sk]};";
            }
        }
        if (($m = $sides($s['margin'] ?? null)) !== null) {
            $css .= "margin:{$m};";
        }
        if (($pd = $sides($s['padding'] ?? null)) !== null) {
            $css .= "padding:{$pd};";
        }
        if (($s['background'] ?? '') !== '') {
            $css .= "background:{$s['background']};";
        }
        if (($s['color'] ?? '') !== '') {
            $css .= "color:{$s['color']};";
        }
        if (($s['radius'] ?? '') !== '') {
            $css .= 'border-radius:'.($radMap[$s['radius']] ?? $s['radius']).';';
        }
        // Positioning on ANY block — style.position/inset/z_index/opacity, like CSS.
        if (($s['position'] ?? '') !== '') {
            $css .= "position:{$s['position']};";
        }
        foreach ((array) ($s['inset'] ?? []) as $side => $v) {
            if ($v !== '' && $v !== null && in_array($side, ['top', 'right', 'bottom', 'left'], true)) {
                $css .= "{$side}:{$v};";
            }
        }
        if (($s['z_index'] ?? '') !== '') {
            $css .= (($s['position'] ?? '') === '' ? 'position:relative;' : '').'z-index:'.((int) $s['z_index']).';';
        }
        if (($s['opacity'] ?? '') !== '') {
            $css .= 'opacity:'.(max(0, min(100, (int) $s['opacity'])) / 100).';';
        }
        if (($s['overflow'] ?? '') !== '') {
            $css .= "overflow:{$s['overflow']};";
        }
        $fc = (array) ($s['flex_child'] ?? []);
        if (isset($fc['grow'])) {
            $css .= "flex-grow:{$fc['grow']};";
        }
        if (isset($fc['shrink'])) {
            $css .= "flex-shrink:{$fc['shrink']};";
        }
        if (($fc['basis'] ?? '') !== '') {
            $css .= "flex-basis:{$fc['basis']};";
        }
        if (($fc['align_self'] ?? '') !== '') {
            $css .= "align-self:{$fc['align_self']};";
        }

        return $css;
    }

    private static function push(string $cls, string $rules): string
    {
        $first = explode(' ', $cls)[0];
        if ($rules !== '') {
            self::$css[] = ".{$first}{{$rules}}";
        }

        return '';
    }
}
