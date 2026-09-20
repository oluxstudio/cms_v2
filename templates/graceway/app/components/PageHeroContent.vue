<script setup lang="ts">
const oluxCms = useOluxContent('page-hero-content')
const oluxFb: Record<string, string> = {}
// Self-contained page hero: the magenta banner + breadcrumbs + copy.
// Content comes from useHeroCopy() by route (survives the CMS page rewrite,
// which drops slots/props); a slot, when present, overrides the map copy.
import { computed, useSlots } from 'vue'

// dynamic pages (e.g. leadership profiles) pass crumbs directly, since their
// route can't be in the useHeroCopy map
const props = defineProps<{ crumbs?: { label: string, to?: string }[] }>()

const route = useRoute()
const slots = useSlots()
const copy = computed(() => useHeroCopy()[route.path.replace(/\/+$/, '') || '/'] ?? null)
const hasSlot = computed(() => !!slots.default)
const crumbItems = computed(() => props.crumbs?.length ? props.crumbs : copy.value?.crumbs)
</script>

<template>
  <section class="page-hero" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <BreadCrumbs v-if="crumbItems?.length" :items="crumbItems" />
      <div class="page-hero-content">
        <slot v-if="hasSlot" />
        <template v-else-if="copy">
          <p class="eyebrow" data-olx-field="eyebrow">{{ copy.eyebrow }}</p>
          <h1 data-olx-field="heading">{{ copy.title }}</h1>
          <p data-olx-field="text">{{ copy.text }}</p>
        </template>
      </div>
    </div>
  </section>
</template>
