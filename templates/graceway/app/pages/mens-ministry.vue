<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import PageHeroContent from '~/components/PageHeroContent.vue'
import MinistryHub from '~/components/MinistryHub.vue'
import MinistryAbout from '~/components/MinistryAbout.vue'
import MinistryGlance from '~/components/MinistryGlance.vue'
import MinistryWhatWeDo from '~/components/MinistryWhatWeDo.vue'
import MinistryPhotos from '~/components/MinistryPhotos.vue'
import MinistryQuestions from '~/components/MinistryQuestions.vue'
import MinistryLeaders from '~/components/MinistryLeaders.vue'
import MinistryVerse from '~/components/MinistryVerse.vue'
import MinistryEvents from '~/components/MinistryEvents.vue'
import MinistryPrayer from '~/components/MinistryPrayer.vue'
import DonateCta from '~/components/DonateCta.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: "Men's Ministry — CAC Blackburn" })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'page-hero-content': PageHeroContent, 'ministry-hub': MinistryHub, 'ministry-about': MinistryAbout, 'ministry-glance': MinistryGlance, 'ministry-what-we-do': MinistryWhatWeDo, 'ministry-photos': MinistryPhotos, 'ministry-questions': MinistryQuestions, 'ministry-leaders': MinistryLeaders, 'ministry-verse': MinistryVerse, 'ministry-events': MinistryEvents, 'ministry-prayer': MinistryPrayer, 'donate-cta': DonateCta, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/mens-ministry', oluxBlocks)
// Page-level literal props (e.g. :limit="3" show-view-all) survive the rewrite.
const oluxProps: Record<string, any> = {  }
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" v-bind="oluxProps[b.key] || {}" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
