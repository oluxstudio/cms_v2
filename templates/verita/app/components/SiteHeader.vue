<script setup lang="ts">
const oluxCms = useOluxContent('site-header')
const oluxFb: Record<string, string> = {"CTA Label":"Book a Call","CTA Link":"/contact"}
const nav = oluxCms.items('Nav', {"Label":"label","Href":"href"}, [
  { label: 'Home', href: '/' },
  { label: 'About', href: '/about' },
  { label: 'Contact', href: '/contact' },
], {})
</script>

<template>
  <header class="site-header" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <NuxtLink to="/" class="logo">Verita<b>.</b></NuxtLink>
      <nav class="nav">
        <NuxtLink v-for="n in nav" :key="n.href" :to="n.href">{{ n.label }}</NuxtLink>
      </nav>
      <a data-olx-field="ctaLabel" class="btn" :href="oluxCms.t('CTA Link', oluxFb['CTA Link'])">{{ oluxCms.t('CTA Label', oluxFb['CTA Label']) }}</a>
    </div>
  </header>
</template>
