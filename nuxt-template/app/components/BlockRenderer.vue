<script setup lang="ts">
import { computed, getCurrentInstance, inject, onBeforeUnmount, onMounted, provide, ref } from 'vue'

/**
 * Recursive renderer for the BlockKit v3 jigsaw tree — one dispatcher switching
 * on block type, exactly mirroring the catalogue (config/blockkit.php).
 * Layout blocks recurse into children; content blocks are leaves. Forms POST to
 * the block-form endpoint; modals/lightboxes live in an overlay layer and open
 * via window events fired by block actions (open_modal / open_lightbox).
 * INTENTIONAL BREAKAGE (cleanup license): the v2 alias types (text, image,
 * embed, h_list, v_list) were removed — no database contains them any more.
 * If a payload ever carries one again, it renders as an unknown-type no-op;
 * reintroduce it as a first-class catalogue type instead of an alias.
 */
const props = defineProps<{ block: any; siteName: string }>()

const b = computed(() => props.block)
const type = computed<string>(() => b.value?.type || 'container')
const p = computed<Record<string, any>>(() => b.value?.props || {})
const kids = computed<any[]>(() => b.value?.children || [])

// ── Style mapping (tokens → CSS) ──
const PAD: Record<string, string> = { none: '0', sm: '12px', md: '28px', lg: '56px', xl: '96px' }
const GAP: Record<string, string> = { none: '0', sm: '8px', md: '20px', lg: '40px' }
const RAD: Record<string, string> = { none: '0', sm: '6px', md: '12px', lg: '20px', full: '9999px' }
const TOKENS: Record<string, string> = {
  'neutral-50': '#f8fafc', 'neutral-100': '#f1f5f9', 'neutral-200': '#e2e8f0',
  'neutral-800': '#1e293b', 'neutral-900': '#0f172a',
  'accent': 'var(--accent, #6366f1)', 'surface': 'var(--surface, #f8fafc)',
}
const color = (v?: string) => (!v ? '' : TOKENS[v] ?? tv(v))

const styleCss = computed(() => {
  const s = b.value?.style || {}
  const out: Record<string, string> = {}
  // Size relative to the parent frame (auto | % | pt | rem | vh | px).
  if (s.width) out.width = String(s.width)
  if (s.height) out.height = String(s.height)
  if (s.min_width) out.minWidth = String(s.min_width)
  if (s.max_width) out.maxWidth = String(s.max_width)
  if (s.min_height) out.minHeight = String(s.min_height)
  if (s.max_height) out.maxHeight = String(s.max_height)
  // Spacing: per-side unit objects; legacy token strings still map through PAD.
  const mg = sides(s.margin)
  if (mg) out.margin = mg
  if (s.padding) out.padding = typeof s.padding === 'string' ? (PAD[s.padding] ?? s.padding) : (sides(s.padding) ?? '0')
  if (s.background) out.background = color(s.background)
  if (s.color) out.color = color(s.color)
  if (s.radius) out.borderRadius = RAD[s.radius] ?? '0'
  // Positioning on ANY block — style.position/inset/z_index/opacity, like CSS.
  if (s.position) out.position = String(s.position)
  const ins = s.inset || {}
  for (const sd of ['top', 'right', 'bottom', 'left']) if (ins[sd]) out[sd] = String(ins[sd])
  if (s.z_index !== undefined && s.z_index !== null && s.z_index !== '') {
    out.zIndex = String(s.z_index)
    if (!out.position) out.position = 'relative'
  }
  if (s.overflow) out.overflow = String(s.overflow)
  if (s.opacity !== undefined && s.opacity !== null && s.opacity !== '') {
    out.opacity = String(Math.max(0, Math.min(100, Number(s.opacity))) / 100)
  }
  // flex_child: how this block behaves inside a flex parent.
  const fc = s.flex_child || {}
  if (fc.grow !== undefined) out.flexGrow = String(fc.grow)
  if (fc.shrink !== undefined) out.flexShrink = String(fc.shrink)
  if (fc.basis) out.flexBasis = String(fc.basis)
  if (fc.align_self) out.alignSelf = String(fc.align_self)
  return tvAll(out)
})

const sides = (v: any): string | undefined => {
  if (!v || typeof v !== 'object') return undefined
  // Each side accepts unit values (%, pt, rem, vh, px) or a spacing token.
  return ['top', 'right', 'bottom', 'left'].map(s => PAD[v[s]] ?? (v[s] || '0')).join(' ')
}

// Theme variables: "$primary" OR "--primary" → var(--bk-primary). :root defines the values.
const tv = (v: any): any => {
  if (typeof v !== 'string' || (!v.includes('$') && !v.includes('--'))) return v
  return v.replace(/\$([a-z][a-z0-9_-]*)/gi, 'var(--bk-$1)')
    .replace(/(^|[^\w(-])--([a-z][a-z0-9_-]*)/gi, '$1var(--bk-$2)')
}

// #rrggbb + opacity % → rgba(); rgba()/named colours pass through.
const alpha = (c: string, pct: number): string => {
  const m = /^#([0-9a-f]{6})$/i.exec((c || '').trim())
  // $theme-variables / named colours: opacity still applies via color-mix
  // (a raw variable would otherwise paint FULLY OPAQUE over the image).
  if (!m) return `color-mix(in srgb, ${c} ${Math.max(0, Math.min(100, pct))}%, transparent)`
  const [r, g, b] = [0, 2, 4].map(i => parseInt(m[1].slice(i, i + 2), 16))
  return `rgba(${r},${g},${b},${Math.max(0, Math.min(100, pct)) / 100})`
}

// Background stack for container-like blocks: overlay tint → gradient → colour.
const bgStack = (out: Record<string, string>, props: any) => {
  const layers: string[] = []
  if (props.overlay) { const ov = alpha(props.overlay, props.overlay_opacity ?? 50); layers.push(`linear-gradient(${ov},${ov})`) }
  // Gradient overlay: a second tint layer with its own opacity — CSS has no
  // per-layer opacity, so every colour token is wrapped in color-mix().
  if (props.overlay_gradient) {
    const gp = Math.max(0, Math.min(100, Number(props.overlay_gradient_opacity ?? 50)))
    layers.push(gp >= 100 ? props.overlay_gradient : String(props.overlay_gradient).replace(
      /#[0-9a-fA-F]{3,8}\b|\$[a-zA-Z][\w-]*|var\(--[\w-]+\)|rgba?\([^)]*\)/g,
      (m) => `color-mix(in srgb, ${m} ${gp}%, transparent)`))
  }
  if (props.gradient) layers.push(props.gradient)
  // background-image from the media library / URL — bottom layer of the stack.
  if (props.bg_image) layers.push(`url('${String(props.bg_image).replace(/['"\\]/g, '')}')`)
  if (layers.length) out.backgroundImage = layers.join(',')
  // Precedence: image/gradient win over the colour (overlay is a tint, not a base).
  if (props.gradient || props.bg_image) { delete out.background; delete out.backgroundColor }
  // CSS filters: blur (px) + percentage filters — emitted only when set.
  const fParts: string[] = []
  if (props.blur !== undefined && props.blur !== null && props.blur !== '') fParts.push(`blur(${Math.max(0, Number(props.blur))}px)`)
  for (const fk of ['brightness', 'contrast', 'saturate', 'grayscale'] as const) {
    if (props[fk] !== undefined && props[fk] !== null && props[fk] !== '') fParts.push(`${fk}(${Math.max(0, Number(props[fk]))}%)`)
  }
  if (fParts.length) out.filter = fParts.join(' ')
  // backdrop-filter: blur — frosts what shows THROUGH the block.
  if (props.backdrop_blur !== undefined && props.backdrop_blur !== null && props.backdrop_blur !== '') {
    const bb = `blur(${Math.max(0, Number(props.backdrop_blur))}px)`
    out.backdropFilter = bb; out.webkitBackdropFilter = bb
  }
  // The rest of the CSS background longhand set — emitted only when set.
  if (props.bg_size) out.backgroundSize = props.bg_size
  if (props.bg_position) out.backgroundPosition = props.bg_position
  if (props.bg_repeat) out.backgroundRepeat = props.bg_repeat
  if (props.bg_attachment) out.backgroundAttachment = props.bg_attachment
  if (props.bg_origin) out.backgroundOrigin = props.bg_origin
  if (props.bg_blend) out.backgroundBlendMode = props.bg_blend
  if (props.bg_clip) {
    out.backgroundClip = props.bg_clip
    if (props.bg_clip === 'text') { out.webkitBackgroundClip = 'text'; out.color = 'transparent'; out.webkitTextFillColor = 'transparent' }
  }
  if (props.z_index !== undefined && props.z_index !== null && props.z_index !== '') {
    out.zIndex = String(props.z_index)
    if (!out.position) out.position = 'relative' // z-index needs a stacking context
  }
  // Whole-block opacity, 0–100 → CSS opacity.
  if (props.opacity !== undefined && props.opacity !== null && props.opacity !== '') {
    out.opacity = String(Math.max(0, Math.min(100, Number(props.opacity))) / 100)
  }
}

const tvAll = (o: Record<string, string>) => { for (const k in o) o[k] = tv(o[k]); return o }

// ── Layout styles ──
const layoutCss = computed(() => {
  const out: Record<string, string> = { ...styleCss.value }
  switch (type.value) {
    case 'container':
      out.padding ||= PAD[p.value.padding ?? 'md']
      if (p.value.background) out.background = color(p.value.background)
      bgStack(out, p.value)
      if (p.value.width) out.width = p.value.width
      if (p.value.height) out.height = p.value.height
      if (p.value.display) out.display = p.value.display
      // Display-specific properties — a container with display:flex/grid gets
      // the SAME options as the dedicated Flex/Grid blocks (real CSS model).
      if (p.value.display === 'flex') {
        const cd = p.value.direction ?? 'row'
        if (typeof cd === 'object' && cd !== null) {
          out['--bk-fd-base'] = String(cd.base ?? 'column')
          out['--bk-fd-md'] = String(cd.md ?? cd.base ?? 'column')
          out['--bk-fd-lg'] = String(cd.lg ?? cd.md ?? cd.base ?? 'column')
        } else out.flexDirection = String(cd)
        out.flexWrap = p.value.flex_wrap ?? 'nowrap'
      if (p.value.background) out.background = color(p.value.background)
      bgStack(out, p.value)
        if (p.value.justify_content) out.justifyContent = p.value.justify_content
        if (p.value.align_items) out.alignItems = p.value.align_items
        if (p.value.align_content) out.alignContent = p.value.align_content
        if (p.value.gap) out.gap = GAP[p.value.gap] ?? p.value.gap
      }
      if (p.value.display === 'grid') {
        const cc = p.value.columns
        const cmap = typeof cc === 'number' ? { base: cc } : (cc || { base: 1 })
        out['--bk-c-base'] = String(cmap.base ?? 1)
        out['--bk-c-md'] = String(cmap.md ?? cmap.base ?? 1)
        out['--bk-c-lg'] = String(cmap.lg ?? cmap.md ?? cmap.base ?? 1)
        if (p.value.gap) out.gap = GAP[p.value.gap] ?? p.value.gap
      }
      if (p.value.position) out.position = p.value.position
      if (p.value.overflow) out.overflow = p.value.overflow
      const inset = p.value.inset || {}
      for (const s of ['top', 'right', 'bottom', 'left']) if (inset[s]) out[s] = inset[s]
      const m = sides(p.value.margin)
      if (m) out.margin = m
      // CSS-faithful: containers are FULL WIDTH by default (like a real div).
      // max_width narrows+centers only when explicitly chosen.
      // Style-level max_width (Size & Position) always WINS over the token.
      if (p.value.max_width && p.value.max_width !== 'full' && !out.maxWidth) {
        out.maxWidth = { sm: '640px', md: '820px', lg: '1100px', xl: '1320px' }[p.value.max_width as string] ?? '1100px'
        if (!m) { out.marginLeft = out.marginRight = 'auto' }
      }
      if (p.value.align) out.textAlign = p.value.align === 'center' ? 'center' : (p.value.align === 'end' ? 'right' : 'left')
      break
    case 'flex': {
      out.display = 'flex'
      // Mobile-first responsive direction: string everywhere, or {base, md, lg}.
      const d = p.value.direction ?? 'row'
      if (typeof d === 'object' && d !== null) {
        out['--bk-fd-base'] = String(d.base ?? 'column')
        out['--bk-fd-md'] = String(d.md ?? d.base ?? 'column')
        out['--bk-fd-lg'] = String(d.lg ?? d.md ?? d.base ?? 'column')
      } else {
        out.flexDirection = String(d)
      }
      out.flexWrap = p.value.flex_wrap ?? 'nowrap'
      if (p.value.justify_content) out.justifyContent = p.value.justify_content
      if (p.value.align_items) out.alignItems = p.value.align_items
      if (p.value.align_content) out.alignContent = p.value.align_content
      out.gap = GAP[p.value.gap ?? 'md']
      break
    }
    case 'grid': {
      out.display = 'grid'
      out.gap = GAP[p.value.gap ?? 'md']
      if (p.value.background) out.background = color(p.value.background)
      bgStack(out, p.value)
      const c = p.value.columns
      const map = typeof c === 'number' ? { base: c } : (c || { base: 1, md: 2, lg: 3 })
      out['--bk-c-base'] = String(map.base ?? 1)
      out['--bk-c-sm'] = String(map.sm ?? map.base ?? 1)
      out['--bk-c-md'] = String(map.md ?? map.sm ?? map.base ?? 1)
      out['--bk-c-lg'] = String(map.lg ?? map.md ?? map.sm ?? map.base ?? 1)
      break
    }
    case 'masonry': {
      const c = p.value.columns
      const map = typeof c === 'number' ? { base: c } : (c || { base: 1, md: 2, lg: 3 })
      out['--bk-c-base'] = String(map.base ?? 1)
      out['--bk-c-md'] = String(map.md ?? map.base ?? 1)
      out['--bk-c-lg'] = String(map.lg ?? map.md ?? map.base ?? 1)
      out['--bk-gap'] = GAP[p.value.gap ?? 'md']
      if (p.value.background) out.background = color(p.value.background)
      bgStack(out, p.value)
      break
    }
    case 'panel':
      out.background = color(p.value.background ?? 'neutral-50')
      out.padding = PAD[p.value.padding ?? 'md']
      out.borderRadius = RAD[p.value.radius ?? 'md']
      if (p.value.bordered) out.border = '1px solid rgba(0,0,0,.12)'
      bgStack(out, p.value)
      break
  }
  return tvAll(out)
})

// ── Navbar: "Label | /url" items ──
const navItems = computed(() => ((p.value.items || []) as string[]).map((raw) => {
  const [label, url] = String(raw).split('|').map(s => s.trim())
  return { label: label || raw, url: url || '#' }
}))
const navCss = computed(() => ({
  ...styleCss.value,
  display: 'flex',
  flexDirection: (p.value.direction ?? 'row') as any,
  gap: ({ sm: '10px', md: '20px', lg: '34px' } as any)[p.value.gap ?? 'md'],
  justifyContent: ({ start: 'flex-start', center: 'center', end: 'flex-end' } as any)[p.value.align ?? 'end'],
  alignItems: 'center',
}))

// ── Block index (id → block) for lightbox media lookups, provided at the root ──
const BK_INDEX = 'bk-block-index'
const parentIndex = inject<Map<string, any> | null>(BK_INDEX, null)
const blockIndex = parentIndex ?? new Map<string, any>()
if (!parentIndex) {
  const walk = (n: any) => { if (n?.id) blockIndex.set(n.id, n); (n?.children || []).forEach(walk) }
  walk(props.block)
  provide(BK_INDEX, blockIndex)
}

// ── Headings / text ──
const headerTag = computed(() => (type.value === 'header' ? (p.value.level ?? 'h2') : 'p'))
const textCss = computed(() => {
  const out: Record<string, string> = { ...styleCss.value }
  if (p.value.font) out.fontFamily = p.value.font
  if (p.value.size) out.fontSize = ({ sm: '.85rem', md: '1rem', lg: '1.2rem' } as any)[p.value.size] ?? p.value.size
  if (p.value.color) out.color = color(p.value.color)
  if (p.value.align) out.textAlign = p.value.align
  // Gradient text: paint the gradient through the glyphs (background-clip:text).
  if (p.value.text_gradient) {
    out.backgroundImage = tv(p.value.text_gradient)
    out.backgroundClip = 'text'; out.webkitBackgroundClip = 'text'
    out.color = 'transparent'; out.webkitTextFillColor = 'transparent'
  }
  return tvAll(out)
})
// Card: shared style + the full background/filter model (like containers).
const cardCss = computed(() => {
  const out: Record<string, string> = { ...styleCss.value }
  if (p.value.background) out.background = color(p.value.background)
  bgStack(out, p.value)
  return tvAll(out)
})

// ── Actions (button / tile / card) ──
const action = computed(() => p.value.action || {})
const actionTarget = computed(() => action.value.target ?? action.value.url ?? '#')
function fireAction(e?: Event) {
  const a = action.value
  if (a.type === 'open_modal') window.dispatchEvent(new CustomEvent('bk-open-modal', { detail: { target: actionTarget.value } }))
  else if (a.type === 'open_lightbox') window.dispatchEvent(new CustomEvent('bk-open-lightbox', { detail: { target: actionTarget.value } }))
  else if (a.type === 'custom_event') window.dispatchEvent(new CustomEvent(a.event || 'bk-event', { detail: a }))
  else if (a.type === 'link' && type.value !== 'button') window.location.href = actionTarget.value
}

// ── Media (image / video / audio) ──
const IMG_SEQ = 'bk-img-seq'
const parentSeq = inject<{ n: number } | null>(IMG_SEQ, null)
const imgSeq = parentSeq ?? { n: 0 }
if (!parentSeq) provide(IMG_SEQ, imgSeq)
const isImg = type.value === 'image' || (type.value === 'media' && (p.value.kind ?? 'image') === 'image')
const imgLoading = isImg && imgSeq.n++ === 0 ? 'eager' : 'lazy'

const placeholder = computed(() =>
  `${(useRuntimeConfig().app?.baseURL || '/').replace(/\/+$/, '/')}placeholder.svg`.replace(/\/+/g, '/'))
const imgSrc = computed(() => {
  const src = p.value.src || p.value.asset_id || ''
  return src && !src.startsWith('@') ? src : placeholder.value
})
const ratioCss = computed(() => {
  const r = p.value.ratio
  // 'original': the asset's own intrinsic size and aspect ratio — no forced
  // frame, no cropping; capped so it never overflows the parent container.
  if (r === 'original') {
    const s = b.value?.style || {}
    // Explicit style width/height still wins over the intrinsic size.
    return { width: s.width || 'auto', height: s.height || 'auto', maxWidth: '100%' }
  }
  if (!r || r === 'auto') return {}
  return { aspectRatio: r.replace(':', ' / '), objectFit: p.value.fit ?? 'cover', width: '100%' }
})
const videoEmbed = computed(() => {
  const src: string = p.value.src || ''
  const yt = src.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]{6,})/)
  if (yt) return `https://www.youtube.com/embed/${yt[1]}${p.value.autoplay ? '?autoplay=1&mute=1' : ''}`
  const vm = src.match(/vimeo\.com\/(\d+)/)
  if (vm) return `https://player.vimeo.com/video/${vm[1]}`
  return null // plain file → native <video>
})

// ── Tile media ──
const tileImg = computed(() => {
  const m = p.value.media || {}
  const src = m.asset_id || ''
  return src && !src.startsWith('@') ? src : placeholder.value
})
const tileRatio = computed(() => ((p.value.media?.ratio ?? '4:3') as string).replace(':', ' / '))

// ── List markers ──
const listMarker = computed(() => p.value.marker ?? 'default')

// ── Modal / lightbox overlay state ──
const overlayOpen = ref(false)
const lbIndex = ref(0)
function onOverlayEvent(e: Event) {
  const target = (e as CustomEvent).detail?.target
  if (target === b.value?.id) { overlayOpen.value = true; lbIndex.value = 0 }
}
onMounted(() => {
  if (type.value === 'modal') window.addEventListener('bk-open-modal', onOverlayEvent)
  if (type.value === 'lightbox') window.addEventListener('bk-open-lightbox', onOverlayEvent)

  // FOUNDATION effects + per-block custom script: stamp data-fx-* from the
  // style bag onto this block's root element, then run the user's script
  // (site-owner authored) with `el` bound to the element.
  const el = getCurrentInstance()?.proxy?.$el as HTMLElement | undefined
  if (el && el.nodeType === 1) {
    const st = b.value?.style || {}
    const map: Record<string, string> = { fx_enter: 'data-fx-enter', fx_leave: 'data-fx-leave', fx_click: 'data-fx-click', fx_duration: 'data-fx-duration', fx_delay: 'data-fx-delay', fx_parallax: 'data-fx-parallax' }
    let hasFx = false
    for (const k in map) {
      if (st[k] !== undefined && st[k] !== null && st[k] !== '') { el.setAttribute(map[k], String(st[k])); hasFx = true }
    }
    if (hasFx) (window as any).__bkFxScan?.()
    const script = b.value?.meta?.script
    if (script && typeof script === 'string' && script.trim() !== '') {
      try { new Function('el', script)(el) } catch (e) { console.error('block script', b.value?.id, e) }
    }
  }
})
onBeforeUnmount(() => {
  window.removeEventListener('bk-open-modal', onOverlayEvent)
  window.removeEventListener('bk-open-lightbox', onOverlayEvent)
})
const lbMedia = computed(() => (p.value.media_ids || [])
  .map((id: string) => blockIndex.get(id))
  .filter(Boolean)
  .map((m: any) => ({
    src: m.props?.src || m.props?.asset_id || '',
    alt: m.props?.alt || '',
  }))
  .map((m: any) => ({ ...m, src: m.src && !m.src.startsWith('@') ? m.src : placeholder.value })))

// ── Form ──
const sending = ref(false)
const done = ref<string | null>(null)
const failed = ref<string | null>(null)
async function submitForm(e: Event) {
  if (type.value !== 'form') return
  sending.value = true
  failed.value = null
  try {
    const data = Object.fromEntries(new FormData(e.target as HTMLFormElement).entries())
    const res = await fetch(`/api/sites/${encodeURIComponent(props.siteName)}/block-form/${encodeURIComponent(b.value.id)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(data),
    })
    const body = await res.json().catch(() => ({}))
    if (res.ok) done.value = body.message || p.value.success_message || 'Thanks!'
    else failed.value = body.message || 'Something went wrong — please check the fields.'
  } catch {
    failed.value = 'Network error — please try again.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <!-- LAYOUT: container / panel / flex / legacy lists / grid -->
  <div v-if="['container', 'panel', 'flex', 'grid'].includes(type)"
       :class="{ 'bk-grid': type === 'grid' || (type === 'container' && p.display === 'grid'), 'bk-flex': type === 'flex' || (type === 'container' && p.display === 'flex') }" :style="layoutCss">
    <BlockRenderer v-for="child in kids" :key="child.id" :block="child" :site-name="siteName" />
  </div>

  <!-- NAVBAR: menu of links -->
  <nav v-else-if="type === 'navbar'" class="bk-navbar" :style="navCss">
    <a v-for="(item, i) in navItems" :key="i" :href="item.url" class="bk-nav-link">{{ item.label }}</a>
  </nav>

  <!-- MASONRY -->
  <div v-else-if="type === 'masonry'" class="bk-masonry" :style="layoutCss">
    <div v-for="child in kids" :key="child.id" class="bk-masonry-item">
      <BlockRenderer :block="child" :site-name="siteName" />
    </div>
  </div>

  <!-- CARD (slotted: media → header → body → footer) -->
  <component :is="p.clickable ? 'a' : 'div'" v-else-if="type === 'card'"
             :href="p.clickable && action.type === 'link' ? actionTarget : undefined"
             :class="['bk-card', `bk-card--${p.variant ?? 'elevated'}`, { 'bk-card--clickable': p.clickable }]"
             :style="cardCss"
             @click="p.clickable && action.type !== 'link' ? fireAction($event) : undefined">
    <div v-for="child in kids" :key="child.id"
         :class="['bk-card-slot', { 'bk-card-slot--media': child.type === 'media' || child.type === 'image' }]">
      <BlockRenderer :block="child" :site-name="siteName" />
    </div>
  </component>

  <!-- MODAL (overlay layer — not in page flow) -->
  <Teleport v-else-if="type === 'modal'" to="body">
    <div v-if="overlayOpen" class="bk-overlay" @click.self="p.dismissible !== false && (overlayOpen = false)">
      <div :class="['bk-modal', `bk-modal--${p.size ?? 'md'}`]" :style="cardCss">
        <div v-if="p.title || p.dismissible !== false" class="bk-modal-head">
          <strong v-if="p.title">{{ p.title }}</strong>
          <button v-if="p.dismissible !== false" class="bk-overlay-close" aria-label="Close" @click="overlayOpen = false">✕</button>
        </div>
        <div class="bk-modal-body">
          <BlockRenderer v-for="child in kids" :key="child.id" :block="child" :site-name="siteName" />
        </div>
      </div>
    </div>
  </Teleport>

  <!-- LIGHTBOX (media gallery overlay) -->
  <Teleport v-else-if="type === 'lightbox'" to="body">
    <div v-if="overlayOpen && lbMedia.length" class="bk-overlay bk-overlay--dark" :style="cardCss" @click.self="overlayOpen = false">
      <button class="bk-overlay-close bk-lb-close" aria-label="Close" @click="overlayOpen = false">✕</button>
      <button v-if="lbMedia.length > 1" class="bk-lb-nav bk-lb-prev" aria-label="Previous"
              @click="lbIndex = (lbIndex - 1 + lbMedia.length) % lbMedia.length">‹</button>
      <figure class="bk-lb-stage">
        <img :src="lbMedia[lbIndex].src" :alt="lbMedia[lbIndex].alt">
        <figcaption v-if="p.show_captions && lbMedia[lbIndex].alt">{{ lbMedia[lbIndex].alt }}</figcaption>
      </figure>
      <button v-if="lbMedia.length > 1" class="bk-lb-nav bk-lb-next" aria-label="Next"
              @click="lbIndex = (lbIndex + 1) % lbMedia.length">›</button>
      <div v-if="p.show_thumbnails !== false && lbMedia.length > 1" class="bk-lb-thumbs">
        <img v-for="(m, i) in lbMedia" :key="i" :src="m.src" :alt="m.alt"
             :class="{ active: i === lbIndex }" @click="lbIndex = i">
      </div>
    </div>
  </Teleport>

  <!-- FORM (functional) -->
  <form v-else-if="type === 'form'" :style="{ ...styleCss, display: 'flex', flexDirection: 'column', gap: '14px' }"
        @submit.prevent="submitForm">
    <p v-if="done" class="bk-form-ok">{{ done }}</p>
    <template v-else>
      <BlockRenderer v-for="child in kids" :key="child.id" :block="child" :site-name="siteName" />
      <p v-if="failed" class="bk-form-err">{{ failed }}</p>
    </template>
  </form>

  <!-- HEADER / CONTENT -->
  <component :is="headerTag" v-else-if="type === 'header'" class="bk-heading" :class="`bk-${p.level ?? 'h2'}`"
             :style="textCss" v-html="p.content" />
  <p v-else-if="type === 'content'" class="bk-body" :style="textCss" v-html="p.content" />

  <!-- TILE -->
  <component :is="action.type === 'link' ? 'a' : 'button'" v-else-if="type === 'tile'"
             :href="action.type === 'link' ? actionTarget : undefined"
             class="bk-tile" :style="styleCss" @click="action.type !== 'link' ? fireAction($event) : undefined">
    <img v-if="p.media" :src="tileImg" :alt="p.label || ''" :style="{ aspectRatio: tileRatio }" loading="lazy">
    <span class="bk-tile-label">{{ p.label }}</span>
    <span v-if="p.sublabel" class="bk-tile-sub">{{ p.sublabel }}</span>
  </component>

  <!-- BUTTON -->
  <button v-else-if="type === 'button' && action.type === 'submit'" type="submit"
          :class="['bk-btn', `bk-btn--${p.style ?? 'primary'}`, `bk-btn--${p.size ?? 'md'}`]" :style="styleCss">{{ p.label }}</button>
  <a v-else-if="type === 'button' && action.type === 'link'" :href="actionTarget"
     :class="['bk-btn', `bk-btn--${p.style ?? 'primary'}`, `bk-btn--${p.size ?? 'md'}`]" :style="styleCss">{{ p.label }}</a>
  <button v-else-if="type === 'button'" type="button"
          :class="['bk-btn', `bk-btn--${p.style ?? 'primary'}`, `bk-btn--${p.size ?? 'md'}`]" :style="styleCss"
          @click="fireAction">{{ p.label }}</button>

  <!-- MEDIA: image / video / audio (+ legacy image) -->
  <img v-else-if="type === 'image' || (type === 'media' && (p.kind ?? 'image') === 'image')"
       :src="imgSrc" :alt="p.alt || p.image_brief || ''"
       :style="{ ...styleCss, ...ratioCss }" class="bk-img" :loading="imgLoading">
  <div v-else-if="type === 'media' && p.kind === 'video' && videoEmbed" class="bk-embed" :style="styleCss">
    <iframe :src="videoEmbed" frameborder="0" allowfullscreen loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" />
  </div>
  <video v-else-if="type === 'media' && p.kind === 'video'" class="bk-video" :style="{ ...styleCss, ...ratioCss }"
         :src="p.src" :controls="p.controls !== false" :autoplay="p.autoplay" :loop="p.loop" :muted="p.autoplay || p.muted" playsinline />
  <audio v-else-if="type === 'media' && p.kind === 'audio'" class="bk-audio" :style="styleCss"
         :src="p.src" :controls="p.controls !== false" :loop="p.loop" />

  <!-- LIST -->
  <component :is="p.ordered ? 'ol' : 'ul'" v-else-if="type === 'list'"
             :class="['bk-list', `bk-list--${listMarker}`, `bk-list--gap-${p.spacing ?? 'md'}`]" :style="styleCss">
    <li v-for="(item, i) in (p.items || [])" :key="i">{{ item }}</li>
  </component>

  <!-- FORM FIELDS -->
  <label v-else-if="type === 'input'" class="bk-field">
    <span>{{ p.label }}<em v-if="p.required"> *</em></span>
    <input :type="p.field_type ?? 'text'" :name="p.name" :required="p.required" :placeholder="p.placeholder || ''">
  </label>
  <label v-else-if="type === 'textarea'" class="bk-field">
    <span>{{ p.label }}<em v-if="p.required"> *</em></span>
    <textarea :name="p.name" :required="p.required" :rows="p.rows ?? 4" />
  </label>
  <label v-else-if="type === 'select'" class="bk-field">
    <span>{{ p.label }}<em v-if="p.required"> *</em></span>
    <select :name="p.name" :required="p.required">
      <option v-for="o in (p.options || [])" :key="o" :value="o">{{ o }}</option>
    </select>
  </label>
  <label v-else-if="type === 'checkbox'" class="bk-field bk-field--check">
    <input type="checkbox" :name="p.name" :required="p.required" value="1">
    <span>{{ p.label }}<em v-if="p.required"> *</em></span>
  </label>

  <!-- WIDGETS -->
  <BookingWidget v-else-if="type === 'booking'" :site-name="siteName"
                 :service="p.service" :headline="p.headline" :intro="p.intro" />

  <EstimatorWidget v-else-if="type === 'estimator'" :site-name="siteName"
                   :trade="p.trade" :headline="p.headline" :intro="p.intro" />

  <hr v-else-if="type === 'divider'" class="bk-divider" :style="{ margin: (GAP as any)[p.spacing ?? 'md'] + ' 0' }">

</template>

<style scoped>
.bk-flex { flex-direction: var(--bk-fd-base, row); }
@media (min-width: 820px)  { .bk-flex { flex-direction: var(--bk-fd-md, var(--bk-fd-base, row)); } }
@media (min-width: 1100px) { .bk-flex { flex-direction: var(--bk-fd-lg, var(--bk-fd-md, row)); } }
.bk-grid { grid-template-columns: repeat(var(--bk-c-base, 1), 1fr); }
@media (min-width: 640px)  { .bk-grid { grid-template-columns: repeat(var(--bk-c-sm, var(--bk-c-base, 1)), 1fr); } }
@media (min-width: 820px)  { .bk-grid { grid-template-columns: repeat(var(--bk-c-md, var(--bk-c-base, 1)), 1fr); } }
@media (min-width: 1100px) { .bk-grid { grid-template-columns: repeat(var(--bk-c-lg, var(--bk-c-md, 1)), 1fr); } }

.bk-masonry { columns: var(--bk-c-base, 1); column-gap: var(--bk-gap, 20px); }
@media (min-width: 820px)  { .bk-masonry { columns: var(--bk-c-md, 2); } }
@media (min-width: 1100px) { .bk-masonry { columns: var(--bk-c-lg, 3); } }
.bk-masonry-item { break-inside: avoid; margin-bottom: var(--bk-gap, 20px); }

/* Card */
.bk-card { --card-pad: 20px; display: block; border-radius: 14px; overflow: hidden; padding: var(--card-pad); background: #fff; color: inherit; text-decoration: none; }
.bk-card--elevated { box-shadow: 0 10px 30px -12px rgba(0,0,0,.18); }
.bk-card--outlined { border: 1px solid rgba(0,0,0,.14); }
.bk-card--flat { background: transparent; }
.bk-card--clickable { cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
.bk-card--clickable:hover { transform: translateY(-3px); box-shadow: 0 16px 40px -14px rgba(0,0,0,.25); }
.bk-card-slot + .bk-card-slot { margin-top: 12px; }
.bk-card-slot--media { margin: calc(-1 * var(--card-pad)); margin-bottom: 12px; }
.bk-card-slot--media :deep(img) { border-radius: 0; width: 100%; }

/* Overlays */
.bk-overlay { position: fixed; inset: 0; z-index: 1000; background: rgba(15,17,26,.55); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; padding: 24px; }
.bk-overlay--dark { background: rgba(10,10,12,.88); backdrop-filter: blur(6px); flex-direction: column; gap: 16px; }
.bk-overlay-close { border: 0; background: rgba(255,255,255,.12); color: #fff; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 14px; }
.bk-modal { background: #fff; color: #111; border-radius: 16px; max-height: 88vh; overflow-y: auto; width: 100%; box-shadow: 0 30px 80px -20px rgba(0,0,0,.4); }
.bk-modal--sm { max-width: 420px; } .bk-modal--md { max-width: 640px; } .bk-modal--lg { max-width: 920px; } .bk-modal--full { max-width: 96vw; height: 92vh; }
.bk-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid rgba(0,0,0,.08); }
.bk-modal-head .bk-overlay-close { background: rgba(0,0,0,.06); color: #333; }
.bk-modal-body { padding: 18px; }

.bk-lb-close { position: absolute; top: 20px; right: 20px; width: 56px; height: 56px; border-radius: 50%; background: var(--accent, #6366f1); color: #fff; font-size: 22px; font-weight: 700; box-shadow: 0 8px 24px rgba(0,0,0,.35); transition: transform .15s ease, filter .15s ease; }
.bk-lb-close:hover { transform: scale(1.08); filter: brightness(1.1); }
.bk-lb-stage { margin: 0; max-width: min(1100px, 92vw); text-align: center; }
.bk-lb-stage img { max-width: 100%; max-height: 68vh; border: 1.5rem solid rgba(255, 249, 238, .22); border-radius: 22px; background: rgba(255, 249, 238, .22); background-clip: border-box; box-shadow: 0 24px 70px rgba(0,0,0,.5); }
.bk-lb-stage figcaption { color: rgba(255, 249, 238, .85); font-size: .9rem; margin-top: 12px; letter-spacing: .02em; }
.bk-lb-nav { position: absolute; top: 50%; transform: translateY(-50%); border: 1px solid rgba(255,249,238,.35); background: rgba(255,249,238,.15); color: rgba(255,249,238,.95); width: 48px; height: 48px; border-radius: 50%; font-size: 26px; cursor: pointer; transition: background .15s ease; }
.bk-lb-nav:hover { background: rgba(255,249,238,.3); }
.bk-lb-prev { left: 18px; } .bk-lb-next { right: 18px; }
.bk-lb-thumbs { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; max-width: 92vw; }
.bk-lb-thumbs img { width: 64px; height: 48px; object-fit: cover; border-radius: 6px; opacity: .5; cursor: pointer; border: 2px solid transparent; }
.bk-lb-thumbs img.active { opacity: 1; border-color: var(--accent, #6366f1); }

/* Headings / text */
.bk-h1 { font-size: clamp(2rem, 5vw, 3.2rem); font-weight: 900; line-height: 1.1; letter-spacing: -.02em; }
.bk-h2 { font-size: clamp(1.6rem, 3.5vw, 2.3rem); font-weight: 800; line-height: 1.2; }
.bk-h3 { font-size: 1.25rem; font-weight: 700; }
.bk-h4, .bk-h5, .bk-h6 { font-weight: 700; }
.bk-body { line-height: 1.7; opacity: .85; }
.bk-caption { font-size: .82rem; opacity: .6; }

/* Tile */
.bk-tile { display: flex; flex-direction: column; gap: 4px; border: 0; background: transparent; padding: 0; text-align: left; cursor: pointer; color: inherit; text-decoration: none; font: inherit; }
.bk-tile img { width: 100%; object-fit: cover; border-radius: 10px; transition: transform .2s ease; }
.bk-tile:hover img { transform: scale(1.03); }
.bk-tile-label { font-weight: 700; font-size: .95rem; margin-top: 6px; }
.bk-tile-sub { font-size: .8rem; opacity: .6; }

.bk-btn { display: inline-block; font-weight: 700; border-radius: 10px; border: 0; cursor: pointer; text-align: center; text-decoration: none; }
.bk-btn--sm { font-size: .8rem; padding: .45rem 1rem; }
.bk-btn--md { font-size: .92rem; padding: .7rem 1.5rem; }
.bk-btn--lg { font-size: 1.05rem; padding: .9rem 2rem; }
.bk-btn--primary { background: var(--accent, #6366f1); color: #fff; }
.bk-btn--secondary { background: transparent; color: var(--accent, #6366f1); border: 2px solid var(--accent, #6366f1); }
.bk-btn--ghost { background: transparent; color: inherit; text-decoration: underline; }

.bk-navbar { flex-wrap: wrap; }
.bk-nav-link { font-weight: 600; font-size: .92rem; color: inherit; text-decoration: none; opacity: .8; transition: opacity .15s ease, color .15s ease; }
.bk-nav-link:hover { opacity: 1; color: var(--accent, #6366f1); }

.bk-img { max-width: 100%; border-radius: 8px; display: block; }
.bk-video { width: 100%; border-radius: 10px; display: block; background: #000; }
.bk-audio { width: 100%; display: block; }

/* List */
.bk-list { padding-left: 1.3rem; line-height: 1.7; }
.bk-list--none { list-style: none; padding-left: 0; }
.bk-list--check { list-style: none; padding-left: 0; }
.bk-list--check li::before { content: '✓'; color: var(--accent, #6366f1); font-weight: 800; margin-right: .6rem; }
.bk-list--arrow { list-style: none; padding-left: 0; }
.bk-list--arrow li::before { content: '→'; color: var(--accent, #6366f1); font-weight: 800; margin-right: .6rem; }
.bk-list--gap-sm li + li { margin-top: 4px; }
.bk-list--gap-md li + li { margin-top: 8px; }
.bk-list--gap-lg li + li { margin-top: 16px; }

.bk-field { display: flex; flex-direction: column; gap: 6px; font-size: .9rem; font-weight: 600; }
.bk-field input, .bk-field textarea, .bk-field select {
  font: inherit; font-weight: 400; padding: .65rem .9rem; border: 1px solid rgba(0,0,0,.15);
  border-radius: 8px; background: #fff; color: #111;
}
.bk-field em { color: #e11d48; font-style: normal; }
.bk-field--check { flex-direction: row; align-items: center; gap: 10px; }
.bk-form-ok { padding: 1rem 1.2rem; border-radius: 10px; background: #ecfdf5; color: #047857; font-weight: 600; }
.bk-form-err { color: #e11d48; font-size: .88rem; }

.bk-divider { border: 0; border-top: 1px solid rgba(0,0,0,.12); }
.bk-embed { position: relative; aspect-ratio: 16 / 9; }
.bk-embed iframe { position: absolute; inset: 0; width: 100%; height: 100%; border-radius: 10px; }
</style>
