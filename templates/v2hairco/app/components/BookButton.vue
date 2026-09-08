<script setup lang="ts">
// Content round-trips through the CMS "Book CTA" component:
// handcoded fallbacks here → cms-sync pushes them → cms-pull → /cms.json.
const { field } = useCms()
</script>

<template>
  <a class="btn" :href="field('bookCta', 'href', '/appointment')" data-olx-key="bookCta" data-olx-kind="component" data-olx-field="href">
    <span data-olx-field="label">{{ field('bookCta', 'label', 'Book an appointment') }}</span>
  </a>
</template>
