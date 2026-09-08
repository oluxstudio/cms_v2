<script setup lang="ts">
const oluxCms = useOluxContent('site-header')
const oluxFb: Record<string, string> = {}
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'

// Nav links come from the CMS "Navigation" collection via /cms.json
// (BrandLogo and BookButton read their own content from the same snapshot).
const { cms } = useCms()

const fallbackNav = oluxCms.items('Fallback Nav', {"Label":"label","Href":"href"}, [
  { label: 'Home', href: '/' },
  { label: 'About', href: '/about' },
  { label: 'Services', href: '/services' },
  { label: 'Appointment', href: '/appointment' },
  { label: 'Contact Us', href: '/contact-us' },
, { label: 'Shop', href: '/shop' }], {})
const nav = computed(() =>
  Array.isArray(cms.value?.nav) && cms.value.nav.length ? cms.value.nav : fallbackNav)

// Elevated header once the page scrolls.
const scrolled = ref(false)
const onScroll = () => { scrolled.value = window.scrollY > 8 }
onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }) })
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

// Mobile menu — closes on navigation.
const open = ref(false)
const route = useRoute()
watch(() => route.path, () => { open.value = false })
</script>

<template>
  <header class="site-header" :class="{ scrolled, open }" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <div class="container">
      <BrandLogo />
      <NavMenu :items="nav" />
      <BookButton />
      <button class="menu-btn" type="button" :aria-expanded="open" aria-label="Menu" @click="open = !open">
        <span /><span /><span />
      </button>
    </div>
    <transition name="drop">
      <div v-if="open" class="mobile-panel">
        <nav class="mobile-nav">
          <NuxtLink v-for="n in nav" :key="n.href" :to="n.href">{{ n.label }}</NuxtLink>
        </nav>
        <BookButton />
      </div>
    </transition>
  </header>
</template>
