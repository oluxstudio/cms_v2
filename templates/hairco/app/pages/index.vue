<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import HeroBlock from '~/components/HeroBlock.vue'
import ServicesBlock from '~/components/ServicesBlock.vue'
import AboutBlock from '~/components/AboutBlock.vue'
import TeamBlock from '~/components/TeamBlock.vue'
import StatsBlock from '~/components/StatsBlock.vue'
import TestimonialsBlock from '~/components/TestimonialsBlock.vue'
import PricingBlock from '~/components/PricingBlock.vue'
import CtaBlock from '~/components/CtaBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Hair Co. — Salon & Hair Care' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'hero': HeroBlock, 'services': ServicesBlock, 'about': AboutBlock, 'team': TeamBlock, 'stats': StatsBlock, 'testimonials': TestimonialsBlock, 'pricing': PricingBlock, 'cta': CtaBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" />
  </div>
</template>
