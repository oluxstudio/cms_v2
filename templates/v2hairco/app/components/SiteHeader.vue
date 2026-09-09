<script setup lang="ts">
const oluxCms = useOluxContent('site-header')
const oluxFb: Record<string, string> = {}
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'

// Split navigation: pages on the left, actions on the right, logo centered.
const leftNav = oluxCms.items('Left Nav', {"Label":"label","Href":"href"}, [
  { label: 'About', href: '/about' },
  { label: 'Services', href: '/services' },
  { label: 'Contact', href: '/contact-us' },
  { label: 'Pricing', href: '/#pricing' },
  { label: 'FAQ', href: '/faq' },
], {})
const rightNav = oluxCms.items('Right Nav', {"Label":"label","Href":"href"}, [
  { label: 'Appointment', href: '/appointment' },
  { label: 'Shop', href: '/shop' },
], {})

// Elevated header once the page scrolls.
const scrolled = ref(false)
const onScroll = () => { scrolled.value = window.scrollY > 8 }
onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }) })
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

// Mobile menu — closes on navigation.
const open = ref(false)
// Live basket badge — shared basket state (useCart also listens for the
// `storage` event, so other tabs stay in sync).
const { cartCount } = useCart()
const route = useRoute()
watch(() => route.path, () => { open.value = false })
</script>

<template>
  <header class="site-header" :class="{ scrolled, open }" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value">
    <div class="container">
      <nav class="nav nav-left">
        <NuxtLink v-for="n in leftNav" :key="n.href" :to="n.href">{{ n.label }}</NuxtLink>
      </nav>
      <div class="logo-slot"><BrandLogo /></div>
      <nav class="nav nav-right">
        <NuxtLink v-for="n in rightNav" :key="n.href" :to="n.href">{{ n.label }}</NuxtLink>
        <a class="cart" href="/shop" :aria-label="`Cart, ${cartCount} items`">Cart <span class="count">{{ cartCount }}</span></a>
      </nav>
      <button class="menu-btn" type="button" :aria-expanded="open" aria-label="Menu" @click="open = !open">
        <span /><span /><span />
      </button>
    </div>
    <transition name="drop">
      <div v-if="open" class="mobile-panel">
        <nav class="mobile-nav">
          <NuxtLink v-for="n in [...leftNav, ...rightNav]" :key="n.href" :to="n.href">{{ n.label }}</NuxtLink>
        </nav>
        <BookButton />
      </div>
    </transition>
  </header>
</template>
