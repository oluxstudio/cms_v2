<script setup lang="ts">
import SiteHeader from '~/components/SiteHeader.vue'
import PageHeroContent from '~/components/PageHeroContent.vue'
import AboutBlock from '~/components/AboutBlock.vue'
import TimesBlock from '~/components/TimesBlock.vue'
import PastorsBlock from '~/components/PastorsBlock.vue'
import DonateCta from '~/components/DonateCta.vue'
import SiteFooter from '~/components/SiteFooter.vue'
import StudiesArchive from '~/components/StudiesArchive.vue'
import BroadcastChannels from '~/components/BroadcastChannels.vue'
import SermonsBlock from '~/components/SermonsBlock.vue'
import CommunityCareOverview from '~/components/CommunityCareOverview.vue'
import ContactBlock from '~/components/ContactBlock.vue'
import ContactMap from '~/components/ContactMap.vue'
import LegalDoc from '~/components/LegalDoc.vue'
import DonateGiving from '~/components/DonateGiving.vue'
import EventsArchive from '~/components/EventsArchive.vue'
import EventsGrid from '~/components/EventsGrid.vue'
import HeroBlock from '~/components/HeroBlock.vue'
import WelcomeBlock from '~/components/WelcomeBlock.vue'
import MinistriesBlock from '~/components/MinistriesBlock.vue'
import EventsBlock from '~/components/EventsBlock.vue'
import BroadcastBlock from '~/components/BroadcastBlock.vue'
import MinistryDetail from '~/components/MinistryDetail.vue'
import MediaMinistryBlock from '~/components/MediaMinistryBlock.vue'
import MinistriesOverview from '~/components/MinistriesOverview.vue'
import JoinChurchBlock from '~/components/JoinChurchBlock.vue'
import PrayerBlock from '~/components/PrayerBlock.vue'
import SermonsArchive from '~/components/SermonsArchive.vue'
import BooksBlock from '~/components/BooksBlock.vue'
import BookStoreBlock from '~/components/BookStoreBlock.vue'
import WorshipShowcase from '~/components/WorshipShowcase.vue'
import YouthLayout from '~/components/YouthLayout.vue'
import BibleStudyBlock from '~/components/BibleStudyBlock.vue'
import PodcastBlock from '~/components/PodcastBlock.vue'
import WorshipBlock from '~/components/WorshipBlock.vue'
import YouthBlock from '~/components/YouthBlock.vue'

const route = useRoute()
const oluxBlocks: Record<string, any> = { 'site-header': SiteHeader, 'page-hero-content': PageHeroContent, 'about': AboutBlock, 'times': TimesBlock, 'pastors': PastorsBlock, 'donate-cta': DonateCta, 'site-footer': SiteFooter, 'studies-archive': StudiesArchive, 'broadcast-channels': BroadcastChannels, 'sermons': SermonsBlock, 'community-care-overview': CommunityCareOverview, 'contact': ContactBlock, 'contact-map': ContactMap, 'legal-doc': LegalDoc, 'donate-giving': DonateGiving, 'events-archive': EventsArchive, 'events-grid': EventsGrid, 'hero': HeroBlock, 'welcome': WelcomeBlock, 'ministries': MinistriesBlock, 'events': EventsBlock, 'broadcast': BroadcastBlock, 'ministry-detail': MinistryDetail, 'media-ministry': MediaMinistryBlock, 'ministries-overview': MinistriesOverview, 'join-church': JoinChurchBlock, 'prayer': PrayerBlock, 'sermons-archive': SermonsArchive, 'books': BooksBlock, 'book-store': BookStoreBlock, 'worship-showcase': WorshipShowcase, 'youth-layout': YouthLayout, 'bible-study': BibleStudyBlock, 'podcast': PodcastBlock, 'worship': WorshipBlock, 'youth': YouthBlock }
// Reactive URL getter — this component is reused across navigations.
const oluxPage = useOluxPageOrder(() => route.path, oluxBlocks)
</script>

<template>
  <div>
    <div id="preloader"></div>
    <component :is="b.comp" v-for="(b, i) in oluxPage" :key="`${b.key}-${i}`" :data-olx-key="b.key" data-olx-kind="component" />
  </div>
</template>
