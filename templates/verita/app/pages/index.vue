<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import HeroBlock from '~/components/HeroBlock.vue'
import ServicesBlock from '~/components/ServicesBlock.vue'
import FaqBlock from '~/components/FaqBlock.vue'
import ContactBlock from '~/components/ContactBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Verita — Clarity in Consulting' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'hero': HeroBlock, 'services': ServicesBlock, 'faq': FaqBlock, 'contact': ContactBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" />
  </div>
</template>
