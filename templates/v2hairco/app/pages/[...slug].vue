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
import QuoteBlock from '~/components/QuoteBlock.vue'
import FaqBlock from '~/components/FaqBlock.vue'
import HeroBlock from '~/components/HeroBlock.vue'
import ServicesBlock from '~/components/ServicesBlock.vue'
import HoursBlock from '~/components/HoursBlock.vue'
import PricingBlock from '~/components/PricingBlock.vue'
import BlogBlock from '~/components/BlogBlock.vue'
import ShopBlock from '~/components/ShopBlock.vue'
import DonateBlock from '~/components/DonateBlock.vue'
import PollBlock from '~/components/PollBlock.vue'
import ProductBlock from '~/components/ProductBlock.vue'

const route = useRoute()
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'about': AboutBlock, 'stats': StatsBlock, 'team': TeamBlock, 'testimonials': TestimonialsBlock, 'cta': CtaBlock, 'site-footer': SiteFooter, 'appointment': AppointmentBlock, 'contact': ContactBlock, 'quote': QuoteBlock, 'faq': FaqBlock, 'hero': HeroBlock, 'services': ServicesBlock, 'hours': HoursBlock, 'pricing': PricingBlock, 'blog': BlogBlock, 'shop': ShopBlock, 'donate': DonateBlock, 'poll': PollBlock, 'product': ProductBlock }
// Reactive URL getter — this component is reused across navigations.
const oluxPage = useOluxPageOrder(() => route.path, oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
