<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import AboutBlock from '~/components/AboutBlock.vue'
import StatsBlock from '~/components/StatsBlock.vue'
import TeamBlock from '~/components/TeamBlock.vue'
import TestimonialsBlock from '~/components/TestimonialsBlock.vue'
import CtaBlock from '~/components/CtaBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'
import AppointmentBlock from '~/components/AppointmentBlock.vue'
import ContactBlock from '~/components/ContactBlock.vue'
import HeroBlock from '~/components/HeroBlock.vue'
import ServicesBlock from '~/components/ServicesBlock.vue'
import PricingBlock from '~/components/PricingBlock.vue'

const route = useRoute()
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'about': AboutBlock, 'stats': StatsBlock, 'team': TeamBlock, 'testimonials': TestimonialsBlock, 'cta': CtaBlock, 'site-footer': SiteFooter, 'appointment': AppointmentBlock, 'contact': ContactBlock, 'hero': HeroBlock, 'services': ServicesBlock, 'pricing': PricingBlock }
// Reactive URL getter — this component is reused across navigations.
const oluxPage = useOluxPageOrder(() => route.path, oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" />
  </div>
</template>
