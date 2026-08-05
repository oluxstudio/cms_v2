<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import ContactBlock from '~/components/ContactBlock.vue'
import FaqBlock from '~/components/FaqBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Contact — Verita' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'contact': ContactBlock, 'faq': FaqBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/contact', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" />
  </div>
</template>
