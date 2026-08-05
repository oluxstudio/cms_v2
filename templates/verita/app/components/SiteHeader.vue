<script setup lang="ts">
const olux = useOluxContent('site-header')
const oluxFb: Record<string, string> = {"CTA Label":"Book a Call","CTA Link":"/contact"}
const nav = olux.items('Nav', {"Label":"label","Href":"href"}, [
  { label: 'Home', href: '/' },
  { label: 'About', href: '/about' },
  { label: 'Contact', href: '/contact' },
], {})
</script>

<template>
  <header class="site-header" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <NuxtLink to="/" class="logo">Verita<b>.</b></NuxtLink>
      <nav class="nav">
        <NuxtLink v-for="n in nav" :key="n.href" :to="n.href">{{ n.label }}</NuxtLink>
      </nav>
      <a class="btn" :href="olux.t('CTA Link', oluxFb['CTA Link'])">{{ olux.t('CTA Label', oluxFb['CTA Label']) }}</a>
    </div>
  </header>
</template>
