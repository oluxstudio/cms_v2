<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import PageHeroContent from '~/components/PageHeroContent.vue'
import JoinChurchBlock from '~/components/JoinChurchBlock.vue'
import TimesBlock from '~/components/TimesBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Join the Church — CAC Blackburn' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'page-hero-content': PageHeroContent, 'join-church': JoinChurchBlock, 'times': TimesBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/newsletter', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
