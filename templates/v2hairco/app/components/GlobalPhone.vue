<script setup lang="ts">
// Global site phone number — ONE value, owned by the CMS "Global Contact"
// component, reusable anywhere: <GlobalPhone /> (text) or <GlobalPhone link />
// (clickable tel: link). Edit it once in the CMS and every usage updates.
// The field() call stays inline in the template so cms-sync can read the
// handcoded fallback as the seed value.
const { field } = useCms()
withDefaults(defineProps<{ link?: boolean }>(), { link: false })
</script>

<template>
  <a v-if="link" :href="`tel:${field('globalContact', 'phone', '+44 7527 685736').replace(/[^+\d]/g, '')}`"
     data-olx-key="globalContact" data-olx-kind="component" data-olx-field="phone">{{ field('globalContact', 'phone', '+44 7527 685736') }}</a>
  <span v-else data-olx-key="globalContact" data-olx-kind="component" data-olx-field="phone">{{ field('globalContact', 'phone', '+44 7527 685736') }}</span>
</template>
