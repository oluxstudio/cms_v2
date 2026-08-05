<script setup lang="ts">
import AppHeader from '~/components/AppHeader.vue'
import HeroBlock from '~/components/HeroBlock.vue'
import ServicesBlock from '~/components/ServicesBlock.vue'
import AboutBlock from '~/components/AboutBlock.vue'
import WhyUsBlock from '~/components/WhyUsBlock.vue'
import PortfolioBlock from '~/components/PortfolioBlock.vue'
import CtaBlock from '~/components/CtaBlock.vue'
import TestimonialsBlock from '~/components/TestimonialsBlock.vue'
import TeamBlock from '~/components/TeamBlock.vue'
import PricingBlock from '~/components/PricingBlock.vue'
import FaqBlock from '~/components/FaqBlock.vue'
import BlogBlock from '~/components/BlogBlock.vue'
import ClientsBlock from '~/components/ClientsBlock.vue'
import ContactBlock from '~/components/ContactBlock.vue'
import AppFooter from '~/components/AppFooter.vue'

// Components are auto-imported by Nuxt from app/components.
useHead({ title: 'Tekstack - IT Solutions & Software Solutions' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'header': AppHeader, 'hero': HeroBlock, 'services': ServicesBlock, 'about': AboutBlock, 'why-us': WhyUsBlock, 'portfolio': PortfolioBlock, 'cta': CtaBlock, 'testimonials': TestimonialsBlock, 'team': TeamBlock, 'pricing': PricingBlock, 'faq': FaqBlock, 'blog': BlogBlock, 'clients': ClientsBlock, 'contact': ContactBlock, 'footer': AppFooter }
const oluxPage = useOluxPageOrder('/', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" />
  </div>
</template>
