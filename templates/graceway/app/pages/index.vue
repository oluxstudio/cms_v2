<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import HeroBlock from '~/components/HeroBlock.vue'
import TimesBlock from '~/components/TimesBlock.vue'
import WelcomeBlock from '~/components/WelcomeBlock.vue'
import MinistriesDirections from '~/components/MinistriesDirections.vue'
import MinistriesBlock from '~/components/MinistriesBlock.vue'
import WorshipBlock from '~/components/WorshipBlock.vue'
import BibleStudyBlock from '~/components/BibleStudyBlock.vue'
import YouthBlock from '~/components/YouthBlock.vue'
import BooksBlock from '~/components/BooksBlock.vue'
import SermonsBlock from '~/components/SermonsBlock.vue'
import EventsBlock from '~/components/EventsBlock.vue'
import BroadcastBlock from '~/components/BroadcastBlock.vue'
import PastorsBlock from '~/components/PastorsBlock.vue'
import DonateCta from '~/components/DonateCta.vue'
import SiteFooter from '~/components/SiteFooter.vue'

useHead({ title: 'CAC Blackburn — Christ Apostolic Church' })

// Blocks render in the CMS-configured order (original order as fallback).
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'hero': HeroBlock, 'times': TimesBlock, 'welcome': WelcomeBlock, 'ministries-directions': MinistriesDirections, 'ministries': MinistriesBlock, 'worship': WorshipBlock, 'bible-study': BibleStudyBlock, 'youth': YouthBlock, 'books': BooksBlock, 'sermons': SermonsBlock, 'events': EventsBlock, 'broadcast': BroadcastBlock, 'pastors': PastorsBlock, 'donate-cta': DonateCta, 'site-footer': SiteFooter }
const oluxPage = useOluxPageOrder('/', oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
