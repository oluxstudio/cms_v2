<script setup lang="ts">
const oluxCms = useOluxContent('site-footer')
const oluxFb: Record<string, string> = {"Text":"Clear advice for growing companies.","Text B":"\u00a9 Verita Consulting. All rights reserved."}
const links = oluxCms.items('Link', {"Label":"label","Href":"href"}, [
  { label: 'Privacy', href: '/privacy' },
  { label: 'Terms', href: '/terms' },
], {})
</script>

<template>
  <footer class="site-footer" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <span class="logo">Verita<b>.</b></span>
      <p data-olx-field="text">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
      <nav class="links">
        <a v-for="l in links" :key="l.href" :href="l.href">{{ l.label }}</a>
      </nav>
      <p data-olx-field="textB" class="copy">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>
    </div>
  </footer>
</template>
