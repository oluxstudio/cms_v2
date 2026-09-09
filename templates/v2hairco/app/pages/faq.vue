<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import FaqBlock from '~/components/FaqBlock.vue'
import CtaBlock from '~/components/CtaBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'FAQ' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'faq': FaqBlock, 'cta': CtaBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/faq', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
