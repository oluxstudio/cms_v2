<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import PageHeroContent from '~/components/PageHeroContent.vue'
import ContactBlock from '~/components/ContactBlock.vue'
import ContactMap from '~/components/ContactMap.vue'
import TimesBlock from '~/components/TimesBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Contact — CAC Blackburn' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'page-hero-content': PageHeroContent, 'contact': ContactBlock, 'contact-map': ContactMap, 'times': TimesBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/contact', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
