<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import HeroBlock from '~/components/HeroBlock.vue'
import ServicesBlock from '~/components/ServicesBlock.vue'
import TeamBlock from '~/components/TeamBlock.vue'
import AboutBlock from '~/components/AboutBlock.vue'
import StatsBlock from '~/components/StatsBlock.vue'
import HoursBlock from '~/components/HoursBlock.vue'
import TestimonialsBlock from '~/components/TestimonialsBlock.vue'
import PricingBlock from '~/components/PricingBlock.vue'
import CtaBlock from '~/components/CtaBlock.vue'
import BlogBlock from '~/components/BlogBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Hair Co. Care — Salon & Hair Care' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'hero': HeroBlock, 'services': ServicesBlock, 'team': TeamBlock, 'about': AboutBlock, 'stats': StatsBlock, 'hours': HoursBlock, 'testimonials': TestimonialsBlock, 'pricing': PricingBlock, 'cta': CtaBlock, 'blog': BlogBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
