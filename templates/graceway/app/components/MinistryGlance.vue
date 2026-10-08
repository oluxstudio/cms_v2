<script setup lang="ts">
const oluxCms = useOluxContent('ministry-glance')
const oluxFb: Record<string, string> = {}
// @olux-per-page — each page keeps its own copy of this block (its own facts)
// @olux-rows — the facts are this block's own rows (add / remove / reorder in Edit site)
// "At a glance" card on a ministry page. Editable in Edit site: the title, the
// button's text and link, the email, and the facts — one row each with an
// icon (calendar, clock, pin, users, star, mic, ticket, tag, mail — or an
// emoji), a label and a value. A fact with an empty value shows this
// ministry's detail from Ministry Pages (When → meets, Where → location, Who
// it's for → audience, Members → members); a fact with no value is hidden.
// The email shows the Ministry Pages address until you type another.
// It renders at the top of the Ministry Hub's sidebar (the hub leaves a slot
// for it); anywhere else as a normal section.
const glanceTitle = oluxCms.tRef('Glance Title', 'At a glance')
const involvedLabel = oluxCms.tRef('Involved Label', 'Get involved')
const involvedLink = oluxCms.tRef('Involved Link', '/contact#get-involved')
const contactEmail = oluxCms.tRef('Contact Email', 'Email from Ministry Pages')

const facts = oluxCms.items('Fact', {"Icon":"icon","Label":"label","Value":"value"}, [
  { icon: 'calendar', label: 'When', value: '' },
  { icon: 'pin', label: 'Where', value: '' },
  { icon: 'users', label: "Who it's for", value: '' },
  { icon: 'star', label: 'Members', value: '' },
], {})

const route = useRoute()
const path = computed(() => route.path.replace(/\/+$/, '') || '/')
const content = useSiteContent()
const d = computed(() => content.ministryDetail[path.value])
const card = computed(() => content.ministriesBento.find(m => m.to === path.value))

const norm = (v: unknown) => String(v ?? '').toLowerCase().replace(/[^a-z0-9]/g, '')
const DETAIL_BY_LABEL: Record<string, string> = { when: 'meets', where: 'location', whoitsfor: 'audience', members: 'members', ledby: 'leader' }
const ICONS = new Set(['calendar', 'clock', 'pin', 'ticket', 'users', 'mic', 'star', 'tag', 'share', 'mail'])
const shown = computed(() => facts.map((row) => {
  const key = DETAIL_BY_LABEL[norm(row.label)]
  const fromDetail = key ? String((d.value as any)?.[key] ?? '') || (key === 'members' ? String(card.value?.members ?? '') : '') : ''
  return { ...row, value: String(row.value ?? '').trim() || fromDetail, svg: ICONS.has(String(row.icon).trim()) }
}).filter(f => f.value))
// a typed address wins; otherwise this ministry's email from Ministry Pages
const emailOf = (typed: string) => (String(typed ?? '').trim().includes('@') ? String(typed).trim() : String(d.value?.email ?? ''))

const inHub = useSidebarSlot('mh-sidebar-glance')
const editing = useEditMode()
</script>

<template>
  <section class="mg-block" :class="{ 'mg-inline': !inHub }" data-olx-panel="ministry-glance" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <Teleport to="#mh-sidebar-glance" :disabled="!inHub">
      <div v-if="shown.length || editing" class="mg-card" data-olx-panel="ministry-glance">
        <p class="mg-title">{{ glanceTitle }}</p>
        <p v-if="!shown.length" class="mg-note" data-olx-skip>No facts to show yet — click here to add them. (Hidden on the live site until there are some.)</p>
        <ul>
          <li v-for="(f, i) in shown" :key="f.label + i" data-olx-item>
            <span class="mg-ico"><EventIcon v-if="f.svg" :name="f.icon.trim() as any" :size="18" /><template v-else>{{ f.icon }}</template></span>
            <div><b>{{ f.label }}</b>{{ f.value }}</div>
          </li>
        </ul>
        <NuxtLink class="btn mg-btn" :to="involvedLink">{{ involvedLabel }}</NuxtLink>
        <a v-if="emailOf(contactEmail)" class="mg-mail" :href="`mailto:${emailOf(contactEmail)}`"><span data-olx-skip>Email </span>{{ emailOf(contactEmail) }}</a>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.mg-block:not(.mg-inline) { display: contents; }
.mg-inline { padding: 0 0 3rem; }
.mg-inline .mg-card { max-width: 420px; margin: 0 auto; }
.mg-card { background: var(--color-secondary); color: #e8eaed; border-radius: 22px; padding: 1.5rem 1.4rem; box-shadow: 0 14px 34px rgba(20, 24, 29, .08); }
.mg-title { font-family: var(--font-heading); font-size: 1.25rem; color: #fff; margin-bottom: .9rem; }
.mg-note { font-size: .9rem; line-height: 1.5; color: #9aa3ad; margin-bottom: 1rem; }
.mg-card ul { list-style: none; padding: 0; margin: 0 0 1.2rem; display: grid; gap: .85rem; }
.mg-card li { display: flex; gap: .75rem; align-items: flex-start; font-size: .95rem; line-height: 1.45; }
.mg-card b { display: block; font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; color: #9aa3ad; margin-bottom: .1rem; }
.mg-ico { width: 36px; height: 36px; flex: none; display: grid; place-items: center; border-radius: 11px; background: rgba(255, 255, 255, .08); color: var(--color-primary); }
.mg-btn { display: block; width: 100%; text-align: center; }
.mg-mail { display: block; margin-top: .8rem; text-align: center; font-size: .88rem; font-weight: 700; color: #fff; opacity: .85; word-break: break-all; }
.mg-mail:hover { opacity: 1; color: var(--color-primary); }
</style>
