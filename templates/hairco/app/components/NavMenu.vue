<script setup lang="ts">
// Links come from the CMS "Navigation" collection (via /cms.json).
// Deliberately NOT marked with data-olx: connect.js hydration re-clones marked
// list items, which strips Vue's router bindings (active classes, SPA nav).
// Nav content stays CMS-owned through the collection + snapshot.
defineProps<{ items: { label: string; href: string }[] }>()
</script>

<template>
  <nav class="nav">
    <NuxtLink v-for="n in items" :key="n.href" :to="n.href">{{ n.label }}</NuxtLink>
  </nav>
</template>
