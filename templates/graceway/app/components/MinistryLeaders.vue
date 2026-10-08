<script setup lang="ts">
const oluxCms = useOluxContent('ministry-leaders')
const oluxFb: Record<string, string> = {}
// @olux-per-page — each page keeps its own copy of this block (its content is per route)
// @olux-source leadership
// "Ministry leaders" — its own component on a ministry page: a title, a link
// and a grid of Leadership members. In Edit site it opens with its fields and
// the Leadership grid ("Items in this block" picks, hides and orders who shows
// on THIS page); without a selection it lists the members whose "Ministries"
// name this ministry. It renders inside the ministry page's sidebar (the
// Ministry Hub leaves a slot for it); anywhere else it shows as a normal section.
const leadersTitle = oluxCms.tRef('Leaders Title', 'Ministry leaders')
const leadersLink = oluxCms.tRef('Leaders Link', 'Meet the whole team')

const route = useRoute()
const path = computed(() => route.path.replace(/\/+$/, '') || '/')
const content = useSiteContent()
const card = computed(() => content.ministriesBento.find(m => m.to === path.value))

// this block's own selection, by its id on this page
const { data: oluxData } = ensureOluxContent()
const blockId = computed(() => {
  const pages: any[] = oluxData.value?.pages ?? []
  const page = pages.find(p => ((p.url || '/').replace(/\/+$/, '') || '/') === path.value)
  return String((page?.wireframe ?? []).find((b: any) => String(b.type || '').endsWith(':ministry-leaders'))?.id ?? '')
})
const cms = useCms()
const norm = (v: unknown) => String(v ?? '').toLowerCase().replace(/[^a-z0-9]/g, '')
const keys = computed(() => new Set([path.value, card.value?.title, card.value?.altName].filter(Boolean).map(norm)))
const leaders = computed(() => {
  const all = useMembers() as (ReturnType<typeof useMembers>[number] & { id?: string })[]
  const ids = cms.viewIds('leadership', { id: blockId.value })
  if (ids) {
    const byId = new Map(all.map(m => [String(m.id ?? ''), m] as const))
    return ids.map(id => byId.get(id)).filter(Boolean) as typeof all
  }
  return all.filter((m) => {
    const list = Array.isArray(m.ministries) ? m.ministries : String(m.ministries ?? '').split(',')
    return list.some(x => keys.value.has(norm(x)))
  })
})
const initials = (name: string) => name.replace(/\(.*?\)/g, '').replace(/^(Rev|Pastor|Bishop|Dr|Mr|Mrs|Ms|Miss)\.?\s+/i, '')
  .split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]?.toUpperCase()).join('')

// into the ministry sidebar when the page has one
const inSidebar = ref(false)
onMounted(() => { inSidebar.value = !!document.getElementById('mh-sidebar-leaders') })
const editing = useEditMode()
</script>

<template>
  <section class="ml-block" :class="{ 'ml-inline': !inSidebar }" data-olx-panel="ministry-leaders" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <Teleport to="#mh-sidebar-leaders" :disabled="!inSidebar">
      <div v-if="leaders.length || editing" class="ml-card" :class="{ 'ms-empty': !leaders.length }" data-olx-panel="ministry-leaders">
        <p class="ml-title">{{ leadersTitle }}</p>
        <p v-if="!leaders.length" class="ms-empty-note" data-olx-skip>No leaders chosen for this page yet — click here to choose them. (Hidden on the live site until there are some.)</p>
        <ul class="ml-list">
          <li v-for="m in leaders" :key="m.slug" data-olx-item>
            <NuxtLink :to="`/leadership/${m.slug}`" class="ml-leader">
              <span class="ml-avatar">
                <img v-if="m.img" :src="m.img" :alt="`Portrait of ${m.name}`" loading="lazy">
                <template v-else>{{ initials(m.name) }}</template>
              </span>
              <span class="ml-text"><b>{{ m.name }}</b><small>{{ m.role }}</small></span>
              <EventIcon name="arrow" :size="15" class="ml-go" />
            </NuxtLink>
          </li>
        </ul>
        <NuxtLink class="ml-link" to="/leadership">{{ leadersLink }} <EventIcon name="arrow" :size="15" /></NuxtLink>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.ml-block:not(.ml-inline) { display: contents; }
.ml-inline { padding: 0 0 3rem; }
.ml-inline .ml-card { max-width: 420px; margin: 0 auto; }
.ml-card { background: #fff; border-radius: 22px; padding: 1.5rem 1.4rem; box-shadow: 0 14px 34px rgba(20, 24, 29, .08); }
.ml-title { font-family: var(--font-heading); font-size: 1.25rem; color: var(--color-secondary); margin-bottom: .9rem; }
.ml-list { list-style: none; padding: 0; margin: 0 0 .4rem; display: grid; }
.ml-leader { display: flex; align-items: center; gap: .8rem; padding: .6rem 0; border-bottom: 1px solid #f0ebe3; color: inherit; }
.ml-list li:last-child .ml-leader { border-bottom: 0; }
.ml-avatar { width: 52px; height: 52px; flex: none; display: grid; place-items: center; border-radius: 50%; overflow: hidden; font-family: var(--font-heading);
  font-size: 1.1rem; color: #fff; background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 35%, var(--color-secondary))); }
.ml-avatar img { width: 100%; height: 100%; object-fit: cover; object-position: top; }
.ml-text { flex: 1; min-width: 0; }
.ml-text b { display: block; color: var(--color-secondary); font-size: 1rem; line-height: 1.3; }
.ml-text small { display: block; color: #6b737c; font-size: .84rem; line-height: 1.35; }
.ml-go { color: #c9c2b8; transition: transform .2s, color .2s; }
.ml-leader:hover b { color: var(--color-primary); }
.ml-leader:hover .ml-go { color: var(--color-primary); transform: translateX(3px); }
.ml-link { display: inline-flex; align-items: center; gap: .35rem; margin-top: .7rem; font-weight: 700; color: var(--color-primary); }
</style>
