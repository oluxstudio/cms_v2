<script setup lang="ts">
// Global site email — ONE value, owned by the CMS "Global Contact" component,
// reusable anywhere: <GlobalEmail /> (text) or <GlobalEmail link /> (mailto:).
// Edit it once in the CMS and every usage updates. The field() call stays
// inline in the template so cms-sync can read the fallback as the seed value.
const { field } = useCms()
withDefaults(defineProps<{ link?: boolean }>(), { link: false })
</script>

<template>
  <a v-if="link" :href="`mailto:${field('globalContact', 'email', 'hello@hairco.salon')}`"
     data-olx-key="globalContact" data-olx-kind="component" data-olx-field="email">{{ field('globalContact', 'email', 'hello@hairco.salon') }}</a>
  <span v-else data-olx-key="globalContact" data-olx-kind="component" data-olx-field="email">{{ field('globalContact', 'email', 'hello@hairco.salon') }}</span>
</template>
