<script setup lang="ts">
const olux = useOluxContent('site-footer')
const oluxFb: Record<string, string> = {"Text":"A salon collective devoted to healthy hair \u2014 honest advice, gentle products and finishes that last.","Subheadline":"Explore","Subheadline B":"Services","Subheadline C":"Contact","Caption":"\u00a9 Hair Co. All rights reserved.","Caption B":"Built with Olux Studio"}
const links = olux.items('Link', {"Label":"label","Href":"href"}, [
  { label: 'About Us', href: '/about' },
  { label: 'Services', href: '/services' },
  { label: 'Appointment', href: '/appointment' },
  { label: 'Contact Us', href: '/contact-us' },
], {})

const services = olux.items('Service', {"Label":"label","Href":"href"}, [
  { label: 'Hairstyles', href: '/services' },
  { label: 'Coloring', href: '/services' },
  { label: 'Hair Curly', href: '/services' },
  { label: 'Lamination', href: '/services' },
], {})
</script>

<template>
  <footer class="site-footer" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="cols">
        <div>
          <NuxtLink to="/" class="logo">Hair Co<b>.</b></NuxtLink>
          <p>{{ olux.t('Text', oluxFb['Text']) }}</p>
        </div>
        <div>
          <h4>{{ olux.t('Subheadline', oluxFb['Subheadline']) }}</h4>
          <ul>
            <li v-for="l in links" :key="l.label"><NuxtLink :to="l.href">{{ l.label }}</NuxtLink></li>
          </ul>
        </div>
        <div>
          <h4>{{ olux.t('Subheadline B', oluxFb['Subheadline B']) }}</h4>
          <ul>
            <li v-for="s in services" :key="s.label"><NuxtLink :to="s.href">{{ s.label }}</NuxtLink></li>
          </ul>
        </div>
        <div>
          <h4>{{ olux.t('Subheadline C', oluxFb['Subheadline C']) }}</h4>
          <ul>
            <li>14 Rosewood Avenue, Portland OR</li>
            <li>+1 589 625 3256</li>
            <li>hello@hairco.salon</li>
          </ul>
        </div>
      </div>
      <div class="fine">
        <span>{{ olux.t('Caption', oluxFb['Caption']) }}</span>
        <span>{{ olux.t('Caption B', oluxFb['Caption B']) }}</span>
      </div>
    </div>
  </footer>
</template>
