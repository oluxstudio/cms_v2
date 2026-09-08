<script setup lang="ts">
// Content round-trips through the CMS "Site Logo" component:
// handcoded fallbacks here → cms-sync pushes them → cms-pull → /cms.json.
const { field } = useCms()
</script>

<template>
  <NuxtLink to="/" class="logo" data-olx-key="siteLogo" data-olx-kind="component">
    <span data-olx-field="text">{{ field('siteLogo', 'text', 'Hair Co') }}</span><b data-olx-field="accent">{{ field('siteLogo', 'accent', '.') }}</b>
  </NuxtLink>
</template>
