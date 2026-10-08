<script setup lang="ts">
// The details card at the top of a sermon / study / song page: title,
// author, publish date, description, plus any extra facts (series,
// scripture, passage…) the page passes in.
defineProps<{
  title: string
  author?: string
  /** publish date & time, ISO */
  date?: string
  description?: string
  /** extra rows, e.g. [{ label: 'Scripture', value: 'John 15:1–17' }] */
  facts?: { label: string, value?: string }[]
  /** what this is — "Sermon", "Bible study", "Song" */
  kind?: string
}>()
</script>

<template>
  <section class="cd-card" aria-label="Details">
    <p v-if="kind" class="eyebrow">{{ kind }} details</p>
    <h2 class="cd-title">{{ title }}</h2>
    <dl class="cd-facts">
      <div v-if="author"><dt>Author</dt><dd>{{ author }}</dd></div>
      <div v-if="date"><dt>Published</dt><dd>{{ publishedOn(date) }}</dd></div>
      <template v-for="f in facts ?? []" :key="f.label">
        <div v-if="f.value"><dt>{{ f.label }}</dt><dd>{{ f.value }}</dd></div>
      </template>
    </dl>
    <div v-if="description" class="cd-desc">
      <h3>Description</h3>
      <p>{{ description }}</p>
    </div>
  </section>
</template>

<style scoped>
.cd-card { background: #fff; border-radius: 22px; padding: 1.8rem 2rem; margin-bottom: 2rem;
  box-shadow: 0 14px 32px rgba(20, 24, 29, .07); border-top: 5px solid var(--color-primary); }
.cd-title { font-size: 1.6rem; line-height: 1.25; margin: .2rem 0 1.1rem; white-space: pre-line; }
.cd-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: .9rem 1.4rem; margin: 0; }
.cd-facts dt { font-size: .75rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #8a929b; margin-bottom: .2rem; }
.cd-facts dd { margin: 0; font-weight: 700; color: var(--color-secondary); }
.cd-desc { margin-top: 1.3rem; padding-top: 1.2rem; border-top: 1px solid #efe9df; }
.cd-desc h3 { font-size: .75rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #8a929b; margin-bottom: .4rem; }
.cd-desc p { font-size: 1.08rem; line-height: 1.65; color: #3d4650; white-space: pre-line; }
</style>
