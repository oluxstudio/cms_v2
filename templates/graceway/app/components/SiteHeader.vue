<script setup lang="ts">
const oluxCms = useOluxContent('site-header')
const oluxFb: Record<string, string> = {}
// single source of truth for the main menu — rendered by both desktop nav and mobile menu.
// `match` lists the path prefixes (sub-pages included) that highlight the item.
// the Ministries submenu mirrors the ministries data source
// About Us gathers the church's own pages (prayer and media live here, not under Ministries)
const aboutChildren__seed = [
  { label: 'Our Story', to: '/about' },
  { label: 'Leadership', to: '/leadership' },
  { label: 'Announcements', to: '/announcements' },
  { label: 'Media & Broadcast', to: '/media-ministry' },
  { label: 'Prayer Watch', to: '/prayer' },
]
// CMS-first (auto-wired at publish): the 'About Children' collection feeds this grid.
const aboutChildren = (() => { const r = useCms().items('aboutChildren', []) as any[]; return r.length ? r : aboutChildren__seed })()

const aboutPaths = aboutChildren.map(c => c.to)
const ministries = useSiteContent().ministriesBento.filter(m => m.to && !aboutPaths.includes(m.to))
// the template's own ministry pages are always in the submenu — even on a site
// whose Ministry Highlights collection predates them
const ministryPages = oluxCms.items('Ministry Page', {"Label":"label","To":"to"}, [{ label: "Men's Ministry", to: '/mens-ministry' }, { label: "Women's Ministry", to: '/womens-ministry' }], {})
const ministryLinks = [
  ...ministries.map(m => ({ label: m.title, to: m.to! })),
  ...ministryPages.filter(p => !ministries.some(m => m.to === p.to)),
]
const menuLinks = oluxCms.items('Menu Link', {"Label":"label","To":"to"}, [
  { label: 'Home', to: '/', match: ['/'] },
  { label: 'About Us', to: '/about', match: aboutPaths, children: aboutChildren },
  { label: 'Ministries', to: '/ministries',
    match: ['/ministries', '/kids', ...ministryLinks.map(l => l.to)],
    children: ministryLinks },
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
    <SiteTopbar />

    <header class="site-header" :class="{ scrolled: scrolled || menuOpen }">
      <div class="container">
        <SiteLogo />
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
