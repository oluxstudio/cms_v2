<script setup lang="ts">
const oluxCms = useOluxContent('ministry-questions')
const oluxFb: Record<string, string> = {}
// @olux-per-page — each page keeps its own copy of this block (its own questions)
// @olux-source faqs
// "Questions people ask" on a ministry page. Its kicker and title are this
// block's own text; the questions come from the site's FAQs collection. In
// Edit site › Items in this block › Show: "All entries", or "Entries matching
// a search or column" (e.g. Category is "Men's Ministry", Tags is "men"), or
// choose the questions one by one. With nothing set it shows the FAQs whose
// category or tags name this ministry. It renders in the Ministry Hub's main
// column (the hub leaves a slot for it); anywhere else as a normal section.
const faqsKicker = oluxCms.tRef('Faqs Kicker', 'Good to know')
const faqsTitle = oluxCms.tRef('Faqs Title', 'Questions people ask')

const route = useRoute()
const path = computed(() => route.path.replace(/\/+$/, '') || '/')
const content = useSiteContent()
const card = computed(() => content.ministriesBento.find(m => m.to === path.value))

const blockId = usePageBlockId('ministry-questions')
const cms = useCms()
const norm = (v: unknown) => String(v ?? '').toLowerCase().replace(/[^a-z0-9]/g, '')
const names = computed(() => new Set([card.value?.title, card.value?.altName].filter(Boolean).map(norm)))
const tagsOf = (f: FaqItem) => (Array.isArray(f.tags) ? f.tags : String(f.tags ?? '').split(','))
const faqs = computed(() => {
  const rows = cms.items('faqs', content.faqs) as (FaqItem & { id?: string })[]
  const ids = cms.viewIds('faqs', { id: blockId.value })
  const list = ids
    ? ids.map(id => rows.find(r => String(r.id ?? '') === id)).filter(Boolean) as FaqItem[]
    : rows.filter(f => names.value.has(norm(f.category)) || tagsOf(f).some(t => names.value.has(norm(t))))
  return list.filter(f => f.q)
})

const inHub = useSidebarSlot('mh-main-faqs')
const editing = useEditMode()
</script>

<template>
  <section class="mq-block" :class="{ 'mq-inline': !inHub }" data-olx-panel="ministry-questions" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <Teleport to="#mh-main-faqs" :disabled="!inHub">
      <div v-if="faqs.length || editing" class="mq-wrap" :class="{ 'mq-empty ms-empty': !faqs.length }" data-olx-panel="ministry-questions">
        <p class="mq-kicker">{{ faqsKicker }}</p>
        <h2>{{ faqsTitle }}</h2>
        <p v-if="!faqs.length" class="ms-empty-note" data-olx-skip>No questions match this section yet — click here and choose which FAQs it shows. (Hidden on the live site until there are some.)</p>
        <div class="mq-list">
          <details v-for="(f, i) in faqs" :key="f.q + i" data-olx-item class="mq-faq" :open="i === 0">
            <summary>{{ f.q }}</summary>
            <p>{{ f.a }}</p>
          </details>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.mq-block:not(.mq-inline) { display: contents; }
.mq-inline { padding: 0 0 3.5rem; }
.mq-inline .mq-wrap { width: min(860px, calc(100% - 2.5rem)); margin: 0 auto; }
.mq-wrap { min-width: 0; }
.mq-empty { border-radius: 22px; padding: 1.5rem 1.4rem; }
.mq-kicker { font-size: .8rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--color-primary); margin-bottom: .5rem; }
.mq-wrap h2 { font-size: clamp(1.6rem, 2.6vw, 2.1rem); margin-bottom: 1.2rem; }
.mq-list { display: grid; gap: .8rem; }
.mq-faq { background: #fff; border-radius: 18px; padding: 1rem 1.3rem; box-shadow: 0 10px 26px rgba(20, 24, 29, .06); }
.mq-faq summary { cursor: pointer; list-style: none; display: flex; justify-content: space-between; gap: 1rem; font-weight: 700; font-size: 1.05rem; color: var(--color-secondary); }
.mq-faq summary::-webkit-details-marker { display: none; }
.mq-faq summary::after { content: '+'; flex: none; width: 26px; height: 26px; display: grid; place-items: center; border-radius: 50%; background: var(--primary-soft); color: var(--color-primary); font-weight: 800; transition: transform .2s; }
.mq-faq[open] summary::after { transform: rotate(45deg); }
.mq-faq p { margin-top: .7rem; color: #55606b; line-height: 1.65; white-space: pre-line; }
</style>
