<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import PageHeroContent from '~/components/PageHeroContent.vue'
import MinistryDetail from '~/components/MinistryDetail.vue'
import DonateCta from '~/components/DonateCta.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Kids — CAC Blackburn' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'page-hero-content': PageHeroContent, 'ministry-detail': MinistryDetail, 'donate-cta': DonateCta, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/kids', oluxBlocks)
// Page-level literal props (e.g. :limit="3" show-view-all) survive the rewrite.
const oluxProps: Record<string, any> = {  }
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" v-bind="oluxProps[b.key] || {}" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
