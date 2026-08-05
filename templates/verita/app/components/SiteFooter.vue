<script setup lang="ts">
const olux = useOluxContent('site-footer')
const oluxFb: Record<string, string> = {"Text":"Clear advice for growing companies.","Text B":"\u00a9 Verita Consulting. All rights reserved."}
const links = olux.items('Link', {"Label":"label","Href":"href"}, [
  { label: 'Privacy', href: '/privacy' },
  { label: 'Terms', href: '/terms' },
], {})
</script>

<template>
  <footer class="site-footer" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <span class="logo">Verita<b>.</b></span>
      <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
      <nav class="links">
        <a v-for="l in links" :key="l.href" :href="l.href">{{ l.label }}</a>
      </nav>
      <p class="copy">{{ olux.t('Text B', oluxFb['Text B']) }}</p>
    </div>
  </footer>
</template>
