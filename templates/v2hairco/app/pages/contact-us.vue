<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import ContactBlock from '~/components/ContactBlock.vue'
import QuoteBlock from '~/components/QuoteBlock.vue'
import StatsBlock from '~/components/StatsBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Contact Us — Hair Co.' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'contact': ContactBlock, 'quote': QuoteBlock, 'stats': StatsBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/contact-us', oluxBlocks)
// Page-level literal props (e.g. :limit="3" show-view-all) survive the rewrite.
const oluxProps: Record<string, any> = {  }
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" v-bind="oluxProps[b.key] || {}" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
