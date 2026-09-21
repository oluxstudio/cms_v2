<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import HeroBlock from '~/components/HeroBlock.vue'
import TimesBlock from '~/components/TimesBlock.vue'
import WelcomeBlock from '~/components/WelcomeBlock.vue'
import MinistriesBlock from '~/components/MinistriesBlock.vue'
import SermonsBlock from '~/components/SermonsBlock.vue'
import EventsBlock from '~/components/EventsBlock.vue'
import BroadcastBlock from '~/components/BroadcastBlock.vue'
import PastorsBlock from '~/components/PastorsBlock.vue'
import DonateCta from '~/components/DonateCta.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'CAC Blackburn — Christ Apostolic Church' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'hero': HeroBlock, 'times': TimesBlock, 'welcome': WelcomeBlock, 'ministries': MinistriesBlock, 'sermons': SermonsBlock, 'events': EventsBlock, 'broadcast': BroadcastBlock, 'pastors': PastorsBlock, 'donate-cta': DonateCta, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/', oluxBlocks)
// Page-level literal props (e.g. :limit="3" show-view-all) survive the rewrite.
const oluxProps: Record<string, any> = { 'pastors': {"limit":3,"showViewAll":true} }
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" v-bind="oluxProps[b.key] || {}" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
