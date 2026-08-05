<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

const { content, error, load } = useSiteContent()
const route = useRoute()

const currentUrl = ref('/')

function initUrl() {
  // Priority: in-app preview hash (#/about) → ?page=/about → route path.
  if (typeof window !== 'undefined' && window.location.hash) {
    currentUrl.value = window.location.hash.slice(1) || '/'
    return
  }
  const q = (route.query.page as string) || ''
  if (q) { currentUrl.value = '/' + q.replace(/^\/+/, ''); return }
  const slug = ([] as string[]).concat(route.params.slug as any || []).join('/')
  currentUrl.value = '/' + slug
}

await load()
onMounted(() => {
  initUrl()
  // The inline preview switches pages by setting the iframe's location.hash.
  window.addEventListener('hashchange', () => {
    currentUrl.value = window.location.hash.slice(1) || '/'
    window.scrollTo({ top: 0, behavior: 'smooth' })
  })
})

const site = computed(() => content.value?.site || null)
const pages = computed(() => content.value?.pages || [])
const currentPage = computed(() =>
  pages.value.find(p => p.url === currentUrl.value) || pages.value[0] || null,
)

// The block tree (already composed with its block layout by the API) is the
// ONLY renderer input — the legacy wireframe/component path is gone. An empty
// or absent tree renders an empty page.
const blockTree = computed(() => (currentPage.value?.block_tree?.children?.length ? currentPage.value.block_tree : null))

// FOUNDATION: page-level custom script (attributes.custom_js) — site-owner
// authored, runs once per page view after the tree mounts.
const ranPageScript = ref('')
watch(currentPage, (pg) => {
  if (! pg || typeof window === 'undefined') return
  const js = pg.attributes?.custom_js
  const key = pg.url + '::' + (js || '')
  if (! js || ranPageScript.value === key) return
  ranPageScript.value = key
  setTimeout(() => { try { new Function(js)() } catch (e) { console.error('page script', e) } }, 50)
}, { immediate: true })

function navigate(url: string) {
  currentUrl.value = url
  if (typeof window !== 'undefined') window.scrollTo({ top: 0, behavior: 'smooth' })
}

// ── Theme → CSS variables (overrides main.css :root) ──
const themeCss = computed(() => {
  const t = site.value?.theme || {}
  const decls = [
    t.accent ? `--accent:${t.accent}` : '',
    t.navy ? `--navy:${t.navy}` : '',
    t.surface ? `--light:${t.surface}` : '',
    t.text ? `--text:${t.text}` : '',
    t.muted ? `--muted:${t.muted}` : '',
    t.radius ? `--radius:${t.radius}` : '',
  ].filter(Boolean).join(';')
  const font = t.font ? `body{font-family:'${t.font}',system-ui,sans-serif}` : ''
  const size = t.base_size ? `html{font-size:${t.base_size}}` : ''
  // User-defined theme variables → --bk-<name>, referenced by blocks as $name.
  const vars = (Array.isArray(t.variables) ? t.variables : [])
    .filter((v: any) => v?.name && v?.value)
    .map((v: any) => `--bk-${v.name}:${v.value}`).join(';')
  return `:root{${decls}${vars ? ';' + vars : ''}}${font}${size}`
})

// ── SEO from page attributes + theme styles ──
useHead(() => ({
  title: currentPage.value
    ? `${currentPage.value.name} — ${site.value?.name ?? 'Site'}`
    : (site.value?.name ?? 'Loading…'),
  meta: [
    { name: 'description', content: currentPage.value?.description || site.value?.description || '' },
    { name: 'keywords', content: currentPage.value?.keywords || '' },
  ],
  // Load the template's web font (e.g. Google Fonts) when the theme supplies a URL.
  link: site.value?.theme?.font_url
    ? [{ key: 'site-font', rel: 'stylesheet', href: site.value.theme.font_url }]
    : [],
  style: [
    { key: 'site-theme', innerHTML: themeCss.value },
    // The template's own stylesheet — injected after the base theme so it reproduces
    // the template's exact design. Sanitised server-side at publish time.
    ...(site.value?.theme?.template_css
      ? [{ key: 'template-css', innerHTML: site.value.theme.template_css }]
      : []),
  ],
}))
</script>

<template>
  <div class="site">
    <div v-if="error" class="center">
      <p>{{ error }}</p>
    </div>

    <template v-else-if="site">
      <!-- The composed block tree IS the whole page — no injected chrome. -->
      <main v-if="currentPage && blockTree" style="position:relative">
        <!-- key: remount per page tree so the eager-image counter starts fresh -->
        <BlockRenderer :key="blockTree.id" :block="blockTree" :site-name="site.name" />
      </main>
      <main v-else-if="currentPage" />
    </template>

    <div v-else class="center"><p>Loading…</p></div>
  </div>
</template>

