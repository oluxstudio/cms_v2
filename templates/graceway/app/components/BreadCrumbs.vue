<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  /** trail items; last one is the current page (no link needed) */
  items?: { label: string, to?: string }[]
}>()

// Rewritten CMS pages render this component without props — build the
// trail from the shared hero-copy map instead.
const route = useRoute()
const items = computed(() =>
  props.items ?? useHeroCopy()[route.path.replace(/\/+$/, '') || '/']?.crumbs ?? [])
</script>

<template>
  <nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
      <li><NuxtLink to="/">Home</NuxtLink></li>
      <li v-for="(item, i) in items" :key="item.label" :aria-current="i === items.length - 1 ? 'page' : undefined">
        <NuxtLink v-if="item.to && i !== items.length - 1" :to="item.to">{{ item.label }}</NuxtLink>
        <span v-else>{{ item.label }}</span>
      </li>
    </ol>
  </nav>
</template>
