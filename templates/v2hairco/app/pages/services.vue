<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import ServicesBlock from '~/components/ServicesBlock.vue'
import AboutBlock from '~/components/AboutBlock.vue'
import PricingBlock from '~/components/PricingBlock.vue'
import CtaBlock from '~/components/CtaBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Services — Hair Co.' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'services': ServicesBlock, 'about': AboutBlock, 'pricing': PricingBlock, 'cta': CtaBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/services', oluxBlocks)
// Page-level literal props (e.g. :limit="3" show-view-all) survive the rewrite.
const oluxProps: Record<string, any> = {  }
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" v-bind="oluxProps[b.key] || {}" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
