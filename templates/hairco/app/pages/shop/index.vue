<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import ShopBlock from '~/components/ShopBlock.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'Shop' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'shop': ShopBlock, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/shop', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
