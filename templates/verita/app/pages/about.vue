<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import AboutBlock from '~/components/AboutBlock.vue'
import TeamBlock from '~/components/TeamBlock.vue'
import ContactBlock from '~/components/ContactBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'About — Verita' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'about': AboutBlock, 'team': TeamBlock, 'contact': ContactBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/about', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" />
  </div>
</template>
