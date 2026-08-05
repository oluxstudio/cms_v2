<?php

/*
|--------------------------------------------------------------------------
| BlockKit catalogue v3 — the single source of truth for the jigsaw model
|--------------------------------------------------------------------------
| Per-type prop schemas used three ways (one definition, three consumers):
|   1. Server-side validation of every mutation (BlockTreeService).
|   2. Injected into the AI assistant's prompt as the block catalogue.
|   3. Driving the manual editors' inspector panels.
|
| Prop schema keys: type (string|bool|int|enum|columns|options|action|sides|object),
| values (enum), default, required, pattern.
| `kind: layout` blocks may have children; `kind: content` blocks are leaves.
|
| v3 spec notes: `grid` and `divider` are deliberate keeps beyond the v3 list
| (aligned rows / visual separation); `header` is the text heading block.
*/

$gap = ['type' => 'enum', 'values' => ['none', 'sm', 'md', 'lg'], 'default' => 'md'];
$actionable = ['type' => 'action']; // {type: link|submit|open_modal|open_lightbox|custom_event, target}

return [

    /*
    | Containment contracts. form: fields, text and one submit button (layout
    | blocks allowed as wrappers of only those). card: slotted composite —
    | at most one direct media child (the media slot).
    */
    'form_children' => ['input', 'textarea', 'select', 'checkbox', 'button', 'content', 'header', 'container', 'panel', 'flex', 'grid', 'divider'],
    'card_children' => ['media', 'header', 'content', 'button', 'list', 'tile', 'flex', 'container', 'panel', 'navbar', 'divider'],

    /*
    | Global per-block style schema (local intent only — global look changes go
    | through theme tokens). Unknown style keys are rejected like unknown props.
    */
    'style' => [
        // Size relative to the parent frame: auto | 0 | <n>% | <n>pt | <n>rem | <n>vh | <n>px
        'width'      => ['type' => 'size'],
        'height'     => ['type' => 'size'],
        // Constraints — like CSS min/max-width/height, same units + $variables.
        'min_width'  => ['type' => 'size'],
        'max_width'  => ['type' => 'size'],
        'min_height' => ['type' => 'size'],
        'max_height' => ['type' => 'size'],
        // Per-side spacing with the same units (legacy sm/md/lg tokens still render).
        'margin'     => ['type' => 'sides'],
        'padding'    => ['type' => 'sides'],
        'background' => ['type' => 'string'], // theme token or hex
        'color'      => ['type' => 'string'],
        'radius'     => ['type' => 'enum', 'values' => ['none', 'sm', 'md', 'lg', 'full']],
        // Positioning — EVERY block, exactly like CSS.
        'position'   => ['type' => 'enum', 'values' => ['static', 'relative', 'absolute', 'sticky', 'fixed']],
        'inset'      => ['type' => 'sides'],
        'z_index'    => ['type' => 'int'],
        'opacity'    => ['type' => 'int'], // 0–100
        // flex_child: how this block behaves inside a flex parent.
        'flex_child' => ['type' => 'object'], // { grow, shrink, basis, align_self }
        'overflow'   => ['type' => 'enum', 'values' => ['visible', 'hidden', 'auto', 'scroll']],
        // ── Effects (FOUNDATION): scroll/click animations + parallax on EVERY block.
        'fx_enter'    => ['type' => 'enum', 'values' => ['fade-in', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'zoom-in', 'blur-in']],
        'fx_leave'    => ['type' => 'enum', 'values' => ['fade-out', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'zoom-out', 'blur-out']],
        'fx_click'    => ['type' => 'enum', 'values' => ['pulse', 'bounce', 'shake', 'flip', 'pop']],
        'fx_duration' => ['type' => 'int'], // ms (default 600)
        'fx_delay'    => ['type' => 'int'], // ms
        'fx_parallax' => ['type' => 'int'], // scroll speed % (-100..100); negative = opposite direction
    ],

    'types' => [

        // ── Layout blocks (accept children) ────────────────────────────────
        'container' => [
            'kind'  => 'layout',
            'name'  => 'Container',
            'icon'  => '▣',
            'description' => 'Plain box — the universal wrapper and page root.',
            'props' => [
                'width'     => ['type' => 'string'],   // auto | 100% | px/rem/%
                'height'    => ['type' => 'string'],   // auto | px/rem/vh
                'display'   => ['type' => 'enum', 'values' => ['block', 'flex', 'grid', 'inline-block', 'none']],
                // ── Display-specific properties: shown (and applied) only when the
                //    matching display is selected — exactly the CSS model.
                'direction'       => ['type' => 'responsive_enum', 'values' => ['row', 'row-reverse', 'column', 'column-reverse'], 'show_if' => ['display' => ['flex']]],
                'flex_wrap'       => ['type' => 'enum', 'values' => ['nowrap', 'wrap', 'wrap-reverse'], 'show_if' => ['display' => ['flex']]],
                'justify_content' => ['type' => 'enum', 'values' => ['flex-start', 'flex-end', 'center', 'space-between', 'space-around', 'space-evenly'], 'show_if' => ['display' => ['flex']]],
                'align_items'     => ['type' => 'enum', 'values' => ['stretch', 'flex-start', 'flex-end', 'center', 'baseline'], 'show_if' => ['display' => ['flex']]],
                'align_content'   => ['type' => 'enum', 'values' => ['stretch', 'flex-start', 'flex-end', 'center', 'space-between', 'space-around'], 'show_if' => ['display' => ['flex']]],
                'gap'             => ['type' => 'enum', 'values' => ['none', 'sm', 'md', 'lg'], 'show_if' => ['display' => ['flex', 'grid']]],
                'columns'         => ['type' => 'columns', 'show_if' => ['display' => ['grid']]],
                'position'  => ['type' => 'enum', 'values' => ['static', 'relative', 'absolute', 'sticky', 'fixed']],
                'inset'     => ['type' => 'sides'],    // {top,right,bottom,left}
                'padding'   => ['type' => 'enum', 'values' => ['none', 'sm', 'md', 'lg', 'xl'], 'default' => 'md'],
                'margin'    => ['type' => 'sides'],
                'background' => ['type' => 'string'],
                // CSS gradient, e.g. linear-gradient(135deg,#4f46e5,#0ea5e9) — layered over background.
                'gradient'   => ['type' => 'string'],
                // Overlay colour + opacity (0–100): a tint layered above background/gradient, below content.
                'overlay'         => ['type' => 'string'],
                'overlay_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay' => ['*']]],
                // Gradient overlay: a second tint layer, its own opacity.
                'overlay_gradient'         => ['type' => 'string'],
                'overlay_gradient_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay_gradient' => ['*']]],
                // ── background-image + the full CSS background longhand set
                //    (https://developer.mozilla.org/…/CSS/Reference/Properties/background).
                //    bg_image: a media-library ref (@media/…) or URL — bottom layer of the stack.
                'bg_image'      => ['type' => 'string'],
                'bg_size'       => ['type' => 'string'],  // cover · contain · auto · 50% · 320px 240px
                'bg_position'   => ['type' => 'string'],  // center · top left · 50% 50% …
                'bg_repeat'     => ['type' => 'enum', 'values' => ['no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round']],
                'bg_attachment' => ['type' => 'enum', 'values' => ['scroll', 'fixed', 'local']],
                'bg_clip'       => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box', 'text']],
                'bg_origin'     => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box']],
                'bg_blend'      => ['type' => 'enum', 'values' => ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity']],
                // ── CSS filters — blur & friends, like the CSS filter property.
                'blur'       => ['type' => 'int'], // px
                'brightness' => ['type' => 'int'], // % (100 = normal)
                'contrast'   => ['type' => 'int'], // %
                'saturate'   => ['type' => 'int'], // %
                'grayscale'  => ['type' => 'int'], // %
                // backdrop-filter: blur — frosts whatever shows THROUGH the block.
                'backdrop_blur' => ['type' => 'int'], // px
                // Stacking order — like CSS z-index (needs position other than static to matter; we default to relative).
                'z_index'    => ['type' => 'int'],
                // Whole-block opacity, 0–100 — like CSS opacity.
                'opacity'    => ['type' => 'int'],
                'overflow'  => ['type' => 'enum', 'values' => ['visible', 'hidden', 'auto', 'scroll']],
                'max_width' => ['type' => 'enum', 'values' => ['sm', 'md', 'lg', 'xl', 'full']],
                'align'     => ['type' => 'enum', 'values' => ['start', 'center', 'end']],
            ],
        ],
        'flex' => [
            'kind'  => 'layout',
            'name'  => 'Flex',
            'icon'  => '⇄',
            'description' => 'One-dimensional row or column layout.',
            'props' => [
                // Mobile-first responsive: a plain value applies everywhere, or per-breakpoint {base, md, lg}.
                'direction'     => ['type' => 'responsive_enum', 'values' => ['row', 'row-reverse', 'column', 'column-reverse'], 'default' => 'row'],
                'flex_wrap'     => ['type' => 'enum', 'values' => ['nowrap', 'wrap', 'wrap-reverse'], 'default' => 'nowrap'],
                'justify_content' => ['type' => 'enum', 'values' => ['flex-start', 'flex-end', 'center', 'space-between', 'space-around', 'space-evenly']],
                'align_items'   => ['type' => 'enum', 'values' => ['stretch', 'flex-start', 'flex-end', 'center', 'baseline']],
                'align_content' => ['type' => 'enum', 'values' => ['stretch', 'flex-start', 'flex-end', 'center', 'space-between', 'space-around']],
                'gap'           => $gap,
                // Same background + filter model as containers.
                'background' => ['type' => 'string'],
                'gradient'   => ['type' => 'string'],
                'overlay'         => ['type' => 'string'],
                'overlay_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay' => ['*']]],
                // Gradient overlay: a second tint layer, its own opacity.
                'overlay_gradient'         => ['type' => 'string'],
                'overlay_gradient_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay_gradient' => ['*']]],
                'bg_image'      => ['type' => 'string'],
                'bg_size'       => ['type' => 'string'],
                'bg_position'   => ['type' => 'string'],
                'bg_repeat'     => ['type' => 'enum', 'values' => ['no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round']],
                'bg_attachment' => ['type' => 'enum', 'values' => ['scroll', 'fixed', 'local']],
                'bg_clip'       => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box', 'text']],
                'bg_origin'     => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box']],
                'bg_blend'      => ['type' => 'enum', 'values' => ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity']],
                'blur'       => ['type' => 'int'], // px
                'brightness' => ['type' => 'int'], // % (100 = normal)
                'contrast'   => ['type' => 'int'], // %
                'saturate'   => ['type' => 'int'], // %
                'grayscale'  => ['type' => 'int'], // %
                'backdrop_blur' => ['type' => 'int'], // px
            ],
        ],
        'grid' => [
            'kind'  => 'layout',
            'name'  => 'Grid',
            'icon'  => '⊞',
            'description' => 'Fixed-column grid — aligned, equal-height rows.',
            'props' => [
                'columns' => ['type' => 'columns', 'default' => ['base' => 1, 'md' => 2, 'lg' => 3]],
                'gap'     => $gap,
                // Same background model as containers: colour → gradient → image + longhands.
                'background' => ['type' => 'string'],
                'gradient'   => ['type' => 'string'],
                'overlay'         => ['type' => 'string'],
                'overlay_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay' => ['*']]],
                // Gradient overlay: a second tint layer, its own opacity.
                'overlay_gradient'         => ['type' => 'string'],
                'overlay_gradient_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay_gradient' => ['*']]],
                'bg_image'      => ['type' => 'string'],
                'bg_size'       => ['type' => 'string'],
                'bg_position'   => ['type' => 'string'],
                'bg_repeat'     => ['type' => 'enum', 'values' => ['no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round']],
                'bg_attachment' => ['type' => 'enum', 'values' => ['scroll', 'fixed', 'local']],
                'bg_clip'       => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box', 'text']],
                'bg_origin'     => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box']],
                'bg_blend'      => ['type' => 'enum', 'values' => ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity']],
                // ── CSS filters — blur & friends, like the CSS filter property.
                'blur'       => ['type' => 'int'], // px
                'brightness' => ['type' => 'int'], // % (100 = normal)
                'contrast'   => ['type' => 'int'], // %
                'saturate'   => ['type' => 'int'], // %
                'grayscale'  => ['type' => 'int'], // %
                // backdrop-filter: blur — frosts whatever shows THROUGH the block.
                'backdrop_blur' => ['type' => 'int'], // px
            ],
        ],
        'masonry' => [
            'kind'  => 'layout',
            'name'  => 'Masonry',
            'icon'  => '▦',
            'description' => 'Masonry grid for mixed-height content.',
            'props' => [
                'columns' => ['type' => 'columns', 'default' => ['base' => 1, 'md' => 2, 'lg' => 3]],
                'gap'     => $gap,
                // Same background + filter model as containers.
                'background' => ['type' => 'string'],
                'gradient'   => ['type' => 'string'],
                'overlay'         => ['type' => 'string'],
                'overlay_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay' => ['*']]],
                'overlay_gradient'         => ['type' => 'string'],
                'overlay_gradient_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay_gradient' => ['*']]],
                'bg_image'      => ['type' => 'string'],
                'bg_size'       => ['type' => 'string'],
                'bg_position'   => ['type' => 'string'],
                'bg_repeat'     => ['type' => 'enum', 'values' => ['no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round']],
                'bg_attachment' => ['type' => 'enum', 'values' => ['scroll', 'fixed', 'local']],
                'bg_clip'       => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box', 'text']],
                'bg_origin'     => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box']],
                'bg_blend'      => ['type' => 'enum', 'values' => ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity']],
                'blur'       => ['type' => 'int'], // px
                'brightness' => ['type' => 'int'], // % (100 = normal)
                'contrast'   => ['type' => 'int'], // %
                'saturate'   => ['type' => 'int'], // %
                'grayscale'  => ['type' => 'int'], // %
                'backdrop_blur' => ['type' => 'int'], // px
            ],
        ],
        'form' => [
            'kind'  => 'layout',
            'name'  => 'Form',
            'icon'  => '✉',
            'description' => 'Form wrapper — fields, text and exactly one submit button.',
            'props' => [
                'submit_action'   => ['type' => 'enum', 'values' => ['collect', 'webhook', 'email'], 'default' => 'collect', 'required' => true],
                'action_target'   => ['type' => 'string'], // URL or email when applicable
                'success_message' => ['type' => 'string', 'default' => 'Thanks — we got your message.', 'required' => true],
                'method'          => ['type' => 'enum', 'values' => ['POST'], 'default' => 'POST'],
            ],
        ],
        'panel' => [
            'kind'  => 'layout',
            'name'  => 'Panel',
            'icon'  => '▢',
            'description' => 'A visible surface — background, padding and radius — holding other blocks.',
            'props' => [
                'background' => ['type' => 'string', 'default' => 'neutral-50'],
                'gradient'   => ['type' => 'string'],
                'overlay'         => ['type' => 'string'],
                'overlay_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay' => ['*']]],
                // Gradient overlay: a second tint layer, its own opacity.
                'overlay_gradient'         => ['type' => 'string'],
                'overlay_gradient_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay_gradient' => ['*']]],
                'bg_image'      => ['type' => 'string'],
                'bg_size'       => ['type' => 'string'],
                'bg_position'   => ['type' => 'string'],
                'bg_repeat'     => ['type' => 'enum', 'values' => ['no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round']],
                'bg_attachment' => ['type' => 'enum', 'values' => ['scroll', 'fixed', 'local']],
                'bg_clip'       => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box', 'text']],
                'bg_origin'     => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box']],
                'bg_blend'      => ['type' => 'enum', 'values' => ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity']],
                // ── CSS filters — blur & friends, like the CSS filter property.
                'blur'       => ['type' => 'int'], // px
                'brightness' => ['type' => 'int'], // % (100 = normal)
                'contrast'   => ['type' => 'int'], // %
                'saturate'   => ['type' => 'int'], // %
                'grayscale'  => ['type' => 'int'], // %
                // backdrop-filter: blur — frosts whatever shows THROUGH the block.
                'backdrop_blur' => ['type' => 'int'], // px
                'z_index'    => ['type' => 'int'],
                'opacity'    => ['type' => 'int'],
                'padding'    => ['type' => 'enum', 'values' => ['none', 'sm', 'md', 'lg', 'xl'], 'default' => 'md'],
                'radius'     => ['type' => 'enum', 'values' => ['none', 'sm', 'md', 'lg'], 'default' => 'md'],
                'bordered'   => ['type' => 'bool', 'default' => false],
            ],
        ],
        'card' => [
            'kind'  => 'layout',
            'name'  => 'Card',
            'icon'  => '▤',
            'description' => 'Slotted composite: media → header → body → footer, with card chrome.',
            'props' => [
                'variant'   => ['type' => 'enum', 'values' => ['elevated', 'outlined', 'flat'], 'default' => 'elevated'],
                'clickable' => ['type' => 'bool', 'default' => false],
                'action'    => $actionable,
                // Same background + filter model as containers.
                'background' => ['type' => 'string'],
                'gradient'   => ['type' => 'string'],
                'overlay'         => ['type' => 'string'],
                'overlay_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay' => ['*']]],
                'overlay_gradient'         => ['type' => 'string'],
                'overlay_gradient_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay_gradient' => ['*']]],
                'bg_image'      => ['type' => 'string'],
                'bg_size'       => ['type' => 'string'],
                'bg_position'   => ['type' => 'string'],
                'bg_repeat'     => ['type' => 'enum', 'values' => ['no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round']],
                'bg_attachment' => ['type' => 'enum', 'values' => ['scroll', 'fixed', 'local']],
                'bg_clip'       => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box', 'text']],
                'bg_origin'     => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box']],
                'bg_blend'      => ['type' => 'enum', 'values' => ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity']],
                'blur'       => ['type' => 'int'], // px
                'brightness' => ['type' => 'int'], // % (100 = normal)
                'contrast'   => ['type' => 'int'], // %
                'saturate'   => ['type' => 'int'], // %
                'grayscale'  => ['type' => 'int'], // %
                'backdrop_blur' => ['type' => 'int'], // px
            ],
        ],
        'modal' => [
            'kind'  => 'layout',
            'name'  => 'Modal',
            'icon'  => '◲',
            'description' => 'Overlay dialog holding any blocks — opened by a trigger action.',
            'props' => [
                'trigger_id'  => ['type' => 'string'],
                'size'        => ['type' => 'enum', 'values' => ['sm', 'md', 'lg', 'full'], 'default' => 'md'],
                'dismissible' => ['type' => 'bool', 'default' => true],
                'title'       => ['type' => 'string'],
                // Same background + filter model as containers.
                'background' => ['type' => 'string'],
                'gradient'   => ['type' => 'string'],
                'overlay'         => ['type' => 'string'],
                'overlay_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay' => ['*']]],
                'overlay_gradient'         => ['type' => 'string'],
                'overlay_gradient_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay_gradient' => ['*']]],
                'bg_image'      => ['type' => 'string'],
                'bg_size'       => ['type' => 'string'],
                'bg_position'   => ['type' => 'string'],
                'bg_repeat'     => ['type' => 'enum', 'values' => ['no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round']],
                'bg_attachment' => ['type' => 'enum', 'values' => ['scroll', 'fixed', 'local']],
                'bg_clip'       => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box', 'text']],
                'bg_origin'     => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box']],
                'bg_blend'      => ['type' => 'enum', 'values' => ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity']],
                'blur'       => ['type' => 'int'], // px
                'brightness' => ['type' => 'int'], // % (100 = normal)
                'contrast'   => ['type' => 'int'], // %
                'saturate'   => ['type' => 'int'], // %
                'grayscale'  => ['type' => 'int'], // %
                'backdrop_blur' => ['type' => 'int'], // px
            ],
        ],

        // ── Content blocks (leaves) ─────────────────────────────────────────
        'header' => [
            'kind'  => 'content',
            'name'  => 'Header',
            'icon'  => 'H',
            'description' => 'Heading text (h1–h6).',
            'props' => [
                'content' => ['type' => 'string', 'required' => true],
                'level'   => ['type' => 'enum', 'values' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], 'default' => 'h2', 'required' => true],
                'font'    => ['type' => 'string'],
                'size'    => ['type' => 'string'],
                'color'   => ['type' => 'string'],
                // linear-gradient(…) painted through the glyphs (background-clip:text).
                'text_gradient' => ['type' => 'string'],
                'align'   => ['type' => 'enum', 'values' => ['left', 'center', 'right']],
            ],
        ],
        'content' => [
            'kind'  => 'content',
            'name'  => 'Content',
            'icon'  => '¶',
            'description' => 'Paragraph text.',
            'props' => [
                'content' => ['type' => 'string', 'required' => true],
                'size'    => ['type' => 'string'],
                'color'   => ['type' => 'string'],
                'text_gradient' => ['type' => 'string'],
                'align'   => ['type' => 'enum', 'values' => ['left', 'center', 'right', 'justify']],
            ],
        ],
        'navbar' => [
            'kind'  => 'content',
            'name'  => 'Navbar',
            'icon'  => '☰',
            'description' => 'A horizontal (or vertical) menu of links — pair it with a logo inside a container.',
            'props' => [
                'items'     => ['type' => 'options', 'required' => true], // "Label | /url" per line
                'direction' => ['type' => 'enum', 'values' => ['row', 'column'], 'default' => 'row'],
                'gap'       => ['type' => 'enum', 'values' => ['sm', 'md', 'lg'], 'default' => 'md'],
                'align'     => ['type' => 'enum', 'values' => ['start', 'center', 'end'], 'default' => 'end'],
            ],
        ],
        'tile' => [
            'kind'  => 'content',
            'name'  => 'Tile',
            'icon'  => '❒',
            'description' => 'Compact clickable unit — media + label (dashboards, galleries, nav grids).',
            'props' => [
                'media'    => ['type' => 'object'],   // { asset_id | image_brief, ratio }
                'label'    => ['type' => 'string', 'required' => true],
                'sublabel' => ['type' => 'string'],
                'action'   => $actionable,
            ],
        ],
        'media' => [
            'kind'  => 'content',
            'name'  => 'Media',
            'icon'  => '🖼',
            'description' => 'Image, video or audio player.',
            'props' => [
                'kind'        => ['type' => 'enum', 'values' => ['image', 'video', 'audio'], 'default' => 'image', 'required' => true],
                'asset_id'    => ['type' => 'string'],
                'image_brief' => ['type' => 'string'],
                'src'         => ['type' => 'string'],
                'alt'         => ['type' => 'string'], // required for images — enforced cross-prop
                'fit'         => ['type' => 'enum', 'values' => ['cover', 'contain'], 'default' => 'cover'],
                // 'original' = the asset's own intrinsic size and aspect ratio (capped at 100% of the parent).
                'ratio'       => ['type' => 'enum', 'values' => ['auto', 'original', '1:1', '4:3', '16:9'], 'default' => 'auto'],
                'controls'    => ['type' => 'bool', 'default' => true],
                'autoplay'    => ['type' => 'bool', 'default' => false],
                'loop'        => ['type' => 'bool', 'default' => false],
                'muted'       => ['type' => 'bool', 'default' => false],
            ],
        ],
        'list' => [
            'kind'  => 'content',
            'name'  => 'List',
            'icon'  => '☰',
            'description' => 'Ordered or unordered list of items.',
            'props' => [
                'ordered' => ['type' => 'bool', 'default' => false],
                'items'   => ['type' => 'options', 'required' => true],
                'marker'  => ['type' => 'enum', 'values' => ['default', 'none', 'check', 'arrow']],
                'spacing' => $gap,
            ],
        ],
        'lightbox' => [
            'kind'  => 'content',
            'name'  => 'Lightbox',
            'icon'  => '⧈',
            'description' => 'Full-size media gallery overlay — references media blocks by id.',
            'props' => [
                'media_ids'       => ['type' => 'options', 'required' => true],
                'show_thumbnails' => ['type' => 'bool', 'default' => true],
                'show_captions'   => ['type' => 'bool', 'default' => false],
                // Same background + filter model as containers (styles the overlay surface).
                'background' => ['type' => 'string'],
                'gradient'   => ['type' => 'string'],
                'overlay'         => ['type' => 'string'],
                'overlay_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay' => ['*']]],
                'overlay_gradient'         => ['type' => 'string'],
                'overlay_gradient_opacity' => ['type' => 'int', 'default' => 50, 'show_if' => ['overlay_gradient' => ['*']]],
                'bg_image'      => ['type' => 'string'],
                'bg_size'       => ['type' => 'string'],
                'bg_position'   => ['type' => 'string'],
                'bg_repeat'     => ['type' => 'enum', 'values' => ['no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round']],
                'bg_attachment' => ['type' => 'enum', 'values' => ['scroll', 'fixed', 'local']],
                'bg_clip'       => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box', 'text']],
                'bg_origin'     => ['type' => 'enum', 'values' => ['border-box', 'padding-box', 'content-box']],
                'bg_blend'      => ['type' => 'enum', 'values' => ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity']],
                'blur'       => ['type' => 'int'], // px
                'brightness' => ['type' => 'int'], // % (100 = normal)
                'contrast'   => ['type' => 'int'], // %
                'saturate'   => ['type' => 'int'], // %
                'grayscale'  => ['type' => 'int'], // %
                'backdrop_blur' => ['type' => 'int'], // px
            ],
        ],
        'button' => [
            'kind'  => 'content',
            'name'  => 'Button',
            'icon'  => '⏺',
            'description' => 'Action button: link, submit, open modal/lightbox or custom event.',
            'props' => [
                'label'  => ['type' => 'string', 'required' => true],
                'action' => $actionable + ['required' => true],
                'style'  => ['type' => 'enum', 'values' => ['primary', 'secondary', 'ghost'], 'default' => 'primary'],
                'size'   => ['type' => 'enum', 'values' => ['sm', 'md', 'lg'], 'default' => 'md'],
            ],
        ],
        'input' => [
            'kind'  => 'content',
            'name'  => 'Input',
            'icon'  => '⌨',
            'description' => 'Single-line form field.',
            'props' => [
                'field_type'  => ['type' => 'enum', 'values' => ['text', 'email', 'tel', 'number', 'date'], 'default' => 'text', 'required' => true],
                'name'        => ['type' => 'string', 'required' => true, 'pattern' => '/^[a-z][a-z0-9_]*$/'],
                'label'       => ['type' => 'string', 'required' => true],
                'required'    => ['type' => 'bool', 'default' => false],
                'placeholder' => ['type' => 'string'],
            ],
        ],
        'textarea' => [
            'kind'  => 'content',
            'name'  => 'Text area',
            'icon'  => '▭',
            'description' => 'Multi-line form field.',
            'props' => [
                'name'     => ['type' => 'string', 'required' => true, 'pattern' => '/^[a-z][a-z0-9_]*$/'],
                'label'    => ['type' => 'string', 'required' => true],
                'required' => ['type' => 'bool', 'default' => false],
                'rows'     => ['type' => 'int', 'default' => 4],
            ],
        ],
        'select' => [
            'kind'  => 'content',
            'name'  => 'Select',
            'icon'  => '▾',
            'description' => 'Dropdown form field.',
            'props' => [
                'name'     => ['type' => 'string', 'required' => true, 'pattern' => '/^[a-z][a-z0-9_]*$/'],
                'label'    => ['type' => 'string', 'required' => true],
                'options'  => ['type' => 'options', 'required' => true],
                'required' => ['type' => 'bool', 'default' => false],
            ],
        ],
        'checkbox' => [
            'kind'  => 'content',
            'name'  => 'Checkbox',
            'icon'  => '☑',
            'description' => 'Single checkbox form field.',
            'props' => [
                'name'     => ['type' => 'string', 'required' => true, 'pattern' => '/^[a-z][a-z0-9_]*$/'],
                'label'    => ['type' => 'string', 'required' => true],
                'required' => ['type' => 'bool', 'default' => false],
            ],
        ],
        'divider' => [
            'kind'  => 'content',
            'name'  => 'Divider',
            'icon'  => '—',
            'description' => 'Horizontal separation line.',
            'props' => [
                'spacing' => $gap,
            ],
        ],
        'booking' => [
            'kind'  => 'content',
            'name'  => 'Booking',
            'icon'  => '📅',
            'description' => 'Booking widget — appointments, stays or trips (needs the Bookings feature).',
            'props' => [
                // Lock the widget to ONE service by slug; blank = customer picks.
                'service'  => ['type' => 'string'],
                'headline' => ['type' => 'string'],
                'intro'    => ['type' => 'string'],
            ],
        ],
        'estimator' => [
            'kind'  => 'content',
            'name'  => 'Cost estimator',
            'icon'  => '🧮',
            'description' => 'Instant cost + completion-time estimate for trade services (needs the Estimator feature).',
            'props' => [
                // Lock the widget to ONE trade by key; blank = visitor picks.
                'trade'    => ['type' => 'string'],
                'headline' => ['type' => 'string'],
                'intro'    => ['type' => 'string'],
            ],
        ],
        'content_slot' => [
            'kind'  => 'slot',
            'name'  => 'Content section',
            'icon'  => '▣',
            'description' => 'Where each page\'s own blocks render. Exactly one per layout; layouts only.',
            'props' => [],
        ],

        // Component slot: a placeholder INSIDE a user component. Placed
        // instances fill it per page; blocks inside it in the component are
        // the default content. Any number per component; components only.
        'slot' => [
            'kind'  => 'layout',
            'name'  => 'Slot',
            'icon'  => '▢',
            'description' => 'Placeholder inside your component — becomes an empty drop area on each page it\'s placed on.',
            'props' => [],
        ],

        // Prop placeholder: dropped INSIDE a component at the exact spot the
        // prop's content should appear. When an instance is placed on a page,
        // the value passed in materializes here as a real block of `kind`.
        'prop' => [
            'kind'  => 'content',
            'name'  => 'Prop',
            'icon'  => '◇',
            'description' => 'A named value passed in when the component is placed — position it where the content should appear.',
            'props' => [
                'name'    => ['type' => 'string', 'required' => true],
                'kind'    => ['type' => 'enum', 'values' => ['heading', 'text', 'button', 'image', 'video'], 'default' => 'heading'],
                'default' => ['type' => 'string'],
            ],
        ],

        // LIVE-LINKED component instance: references a user component by id.
        // The component's tree renders through it at view time — edit the
        // component once, every placed copy updates. Per-instance state lives
        // here: prop overrides + slot content (this block's children).
        'component_ref' => [
            'kind'  => 'layout',
            'name'  => 'Component',
            'icon'  => '⚙',
            'description' => 'A live instance of one of your components.',
            'props' => [
                'component_id' => ['type' => 'int', 'required' => true],
                'overrides'    => ['type' => 'object'], // { prop name => value }
            ],
        ],
    ],
];
