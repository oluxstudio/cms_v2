<script setup lang="ts">
const oluxCms = useOluxContent('legal-doc')
const oluxFb: Record<string, string> = {}
// Legal document page body — content comes from the global data source,
// keyed by route (/privacy-policy, /terms, /cookie-policy).
const route = useRoute()
const { legal } = useSiteContent()
const doc = computed(() => legal[route.path.replace(/\/+$/, '')] ?? null)
</script>

<template>
  <!-- no v-if on the root: the CMS pipeline injects its own root v-if on intake -->
  <section class="legal-doc" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div v-if="doc" class="container">
      <p class="legal-updated">{{ doc.updated }}</p>
      <p class="legal-intro">{{ doc.intro }}</p>
      <div v-for="s in doc.sections" :key="s.heading" class="legal-section">
        <h2>{{ s.heading }}</h2>
        <p>{{ s.body }}</p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.legal-doc { padding: 3rem 0; }
.legal-doc .container { max-width: 780px; }
.legal-updated { font-size: .9rem; color: var(--color-default); opacity: .7; margin-bottom: 1.2rem; }
.legal-intro { font-size: 1.05rem; line-height: 1.7; margin-bottom: 2rem; }
.legal-section { margin-bottom: 1.8rem; }
.legal-section h2 { font-size: 1.2rem; color: var(--color-secondary); margin-bottom: .5rem; }
.legal-section p { line-height: 1.7; color: var(--color-default); white-space: pre-line; }
</style>
