<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import AboutBlock from '~/components/AboutBlock.vue'
import TeamBlock from '~/components/TeamBlock.vue'
import ContactBlock from '~/components/ContactBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'
import FaqBlock from '~/components/FaqBlock.vue'
import HeroBlock from '~/components/HeroBlock.vue'
import ServicesBlock from '~/components/ServicesBlock.vue'

const route = useRoute()
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'about': AboutBlock, 'team': TeamBlock, 'contact': ContactBlock, 'site-footer': SiteFooter, 'faq': FaqBlock, 'hero': HeroBlock, 'services': ServicesBlock }
// Reactive URL getter — this component is reused across navigations.
const oluxPage = useOluxPageOrder(() => route.path, oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" />
  </div>
</template>
