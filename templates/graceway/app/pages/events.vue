<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import PageHeroContent from '~/components/PageHeroContent.vue'
import EventsGrid from '~/components/EventsGrid.vue'
import SermonsBlock from '~/components/SermonsBlock.vue'
import DonateCta from '~/components/DonateCta.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Events — CAC Blackburn' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'page-hero-content': PageHeroContent, 'events-grid': EventsGrid, 'sermons': SermonsBlock, 'donate-cta': DonateCta, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/events', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
