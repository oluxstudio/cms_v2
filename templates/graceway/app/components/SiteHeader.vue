<script setup lang="ts">
const oluxCms = useOluxContent('site-header')
const oluxFb: Record<string, string> = {"Phone":"+1 705 55 50 000","Phone Link":"tel:+17055550000","Caption":"121 Wallstreet street, NY York, USA","Image":"/assets/images/logo.png"}
// single source of truth for the main menu — rendered by both desktop nav and mobile menu.
// `match` lists the path prefixes (sub-pages included) that highlight the item.
// the Ministries submenu mirrors the ministries data source
const ministries = useSiteContent().ministriesBento
const menuLinks = oluxCms.items('Menu Link', {"Label":"label","To":"to"}, [
  { label: 'Home', to: '/', match: ['/'] },
  { label: 'About Us', to: '/about', match: ['/about', '/leadership']},
  { label: 'Ministries', to: '/ministries',
    match: ['/ministries', '/kids', ...ministries.map(m => m.to).filter(Boolean) as string[]],
    children: ministries.filter(m => m.to).map(m => ({ label: m.title, to: m.to! })) },
  { label: 'Events', to: '/events', match: ['/events', '/event-archive', '/broadcast'], children: [
    { label: 'Upcoming Events', to: '/events' },
    { label: 'Event Archive', to: '/event-archive' },
    { label: 'Live Broadcast', to: '/broadcast' },
  ] },
  { label: 'Sermons', to: '/sermons', match: ['/sermons']},
  { label: 'Store', to: '/store', match: ['/store'] },
  { label: 'Contact Us', to: '/contact', match: ['/contact', '/newsletter']},
], {})

// mobile: which submenu is expanded
const openSub = ref<string | null>(null)

// a menu item is active when the current path is one of its prefixes (Home only on exact '/')
// CMS-rebuilt menu items may carry only label/to — fall back to the link
// target itself so a missing match array can never crash the header.
// a submenu entry is active on its page or a sub-path of it; among siblings
// the LONGEST matching path wins (so /events/archive doesn't also light /events)
const matches = (to: string) => route.path === to || route.path.startsWith(to + '/')
const isSubActive = (c: any, siblings: any[] = []) => matches(c.to)
  && !siblings.some(o => o.to !== c.to && o.to.length > c.to.length && matches(o.to))

const isActive = (link: any) =>
  ((link.match ?? [link.to]) as string[]).filter(Boolean).some(m => m === '/' ? route.path === '/' : route.path === m || route.path.startsWith(m + '/'))

const menuOpen = ref(false)
const route = useRoute()
// close the menu whenever navigation happens
watch(() => route.fullPath, () => { menuOpen.value = false })

// transparent header at the top of the page; solid once scrolled
const scrolled = ref(false)
const onScroll = () => { scrolled.value = window.scrollY > 10 }
onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }) })
onUnmounted(() => window.removeEventListener('scroll', onScroll))
</script>

<template>
  <!-- display:contents so the sticky header positions against the page, not this wrapper -->
  <div class="header-wrap" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <!-- Top bar: contact info + social links (scrolls away; the main header stays sticky) -->
    <div class="topbar">
      <div class="container">
        <ul class="topbar-info">
          <li>
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.6.1.3 0 .7-.2 1l-2.3 2.2z"/></svg>
            <a data-olx-field="phone" :href="oluxCms.t('Phone Link', oluxFb['Phone Link'])">{{ oluxCms.t('Phone', oluxFb['Phone']) }}</a>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
            <span data-olx-field="caption">{{ oluxCms.t('Caption', oluxFb['Caption']) }}</span>
          </li>
        </ul>
        <div class="topbar-social">
          <a href="#" aria-label="YouTube"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M23 7.2s-.2-1.6-.9-2.3c-.9-.9-1.9-.9-2.3-1C16.6 3.6 12 3.6 12 3.6s-4.6 0-7.8.3c-.4.1-1.4.1-2.3 1-.7.7-.9 2.3-.9 2.3S.8 9.1.8 11v1.8c0 1.9.2 3.8.2 3.8s.2 1.6.9 2.3c.9.9 2 .9 2.5 1 1.8.2 7.6.3 7.6.3s4.6 0 7.8-.4c.4-.1 1.4-.1 2.3-1 .7-.7.9-2.3.9-2.3s.2-1.9.2-3.8V11c0-1.9-.2-3.8-.2-3.8zM9.9 15.1V8.4l6.2 3.4-6.2 3.3z"/></svg></a>
          <a href="#" aria-label="X (Twitter)"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.2 2.3h3.3l-7.3 8.3L22.8 22h-6.7l-5.3-6.9L4.8 22H1.5l7.8-8.9L1.1 2.3H8l4.8 6.3 5.4-6.3zm-1.2 17.7h1.8L7 4.2H5l12 15.8z"/></svg></a>
          <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-9h3l.5-3.5h-3.5V7.2c0-1 .3-1.7 1.8-1.7H17V2.2c-.3 0-1.4-.2-2.6-.2-2.6 0-4.4 1.6-4.4 4.5v3H7V13h3v9h3.5z"/></svg></a>
          <a href="#" aria-label="RSS"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 4.4v3.2c6.8 0 12.4 5.6 12.4 12.4h3.2C19.6 11.4 12.6 4.4 4 4.4zm0 6.4v3.2a5.6 5.6 0 0 1 5.6 5.6h3.2c0-4.9-3.9-8.8-8.8-8.8zM6.2 15.6a2.2 2.2 0 1 0 0 4.4 2.2 2.2 0 0 0 0-4.4z"/></svg></a>
        </div>
      </div>
    </div>

    <header class="site-header" :class="{ scrolled: scrolled || menuOpen }">
      <div class="container">
        <NuxtLink to="/" class="logo">
        <img data-olx-field="image" :src="oluxCms.t('Image', oluxFb['Image'])" alt="Church logo" class="logo-img" onerror="this.remove()">
        <span class="logo-text">CAC  <br/>Mount Zion<br/>International<small>Blackburn.</small></span>
      </NuxtLink>
        <nav class="site-nav">
          <div v-for="l in menuLinks" :key="l.label" class="nav-item">
            <NuxtLink :to="l.to" :class="{ active: isActive(l) }">
              {{ l.label }}<span v-if="l.children" class="caret" aria-hidden="true"> ▾</span>
            </NuxtLink>
            <div v-if="l.children" class="dropdown">
              <NuxtLink v-for="c in l.children" :key="c.label" :to="c.to" :class="{ active: isSubActive(c, l.children) }"><span v-if="c.icon" class="mi-icon" aria-hidden="true">{{ c.icon }}</span>{{ c.label }}</NuxtLink>
            </div>
          </div>
        </nav>
        <div class="header-cta">
          <NuxtLink class="btn light" to="/newsletter">Join the Church</NuxtLink>
        </div>
        <button class="burger" :class="{ open: menuOpen }" :aria-expanded="menuOpen" aria-label="Toggle menu" @click="menuOpen = !menuOpen">
          <span></span><span></span><span></span>
        </button>
      </div>

      <!-- Mobile slide-down menu -->
      <Transition name="menu-slide">
        <nav v-if="menuOpen" class="mobile-menu">
          <template v-for="l in menuLinks" :key="l.label">
            <div class="mm-row">
              <NuxtLink :to="l.to" :class="{ active: isActive(l) }">{{ l.label }}</NuxtLink>
              <button
                v-if="l.children" type="button" class="mm-toggle" :class="{ open: openSub === l.label }"
                :aria-expanded="openSub === l.label" :aria-label="`Toggle ${l.label} submenu`"
                @click="openSub = openSub === l.label ? null : l.label"
              >▾</button>
            </div>
            <div v-if="l.children && openSub === l.label" class="mm-sub">
              <NuxtLink v-for="c in l.children" :key="c.label" :to="c.to" :class="{ active: isSubActive(c, l.children) }"><span v-if="c.icon" class="mi-icon" aria-hidden="true">{{ c.icon }}</span>{{ c.label }}</NuxtLink>
            </div>
          </template>
          <NuxtLink class="btn dark" to="/newsletter">Join the Church</NuxtLink>
        </nav>
      </Transition>
    </header>
  </div>
</template>
