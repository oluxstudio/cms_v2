<script setup lang="ts">
const oluxCms = useOluxContent('site-footer')
const oluxFb: Record<string, string> = {"Subheadline":"Explore","Subheadline B":"Services","Subheadline C":"Contact","Caption":"\u00a9 Hair Co. All rights reserved.","Caption B":"Built with Olux Studio"}
const { field } = useCms()

const links = oluxCms.items('Link', {"Label":"label","Href":"href"}, [
  { label: 'About Us', href: '/about' },
  { label: 'Services', href: '/services' },
  { label: 'Appointment', href: '/appointment' },
  { label: 'Contact Us', href: '/contact-us' },
], {})

const services = oluxCms.items('Service', {"Label":"label","Href":"href"}, [
  { label: 'Hairstyles', href: '/services' },
  { label: 'Coloring', href: '/services' },
  { label: 'Hair Curly', href: '/services' },
  { label: 'Lamination', href: '/services' },
], {})
</script>

<template>
  <footer class="site-footer" data-olx-key="footer" data-olx-kind="component" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="cols">
        <div>
          <NuxtLink to="/" class="logo">Hair Co<b>.</b></NuxtLink>
          <p data-olx-field="body">{{ field('footer', 'body', 'A salon collective devoted to healthy hair — honest advice, gentle products and finishes that last.') }}</p>
        </div>
        <div>
          <h4 data-olx-field="subheadline">{{ oluxCms.t('Subheadline', oluxFb['Subheadline']) }}</h4>
          <ul>
            <li v-for="l in links" :key="l.label"><NuxtLink :to="l.href">{{ l.label }}</NuxtLink></li>
          </ul>
        </div>
        <div>
          <h4 data-olx-field="subheadlineB">{{ oluxCms.t('Subheadline B', oluxFb['Subheadline B']) }}</h4>
          <ul>
            <li v-for="s in services" :key="s.label"><NuxtLink :to="s.href">{{ s.label }}</NuxtLink></li>
          </ul>
        </div>
        <div>
          <h4 data-olx-field="subheadlineC">{{ oluxCms.t('Subheadline C', oluxFb['Subheadline C']) }}</h4>
          <ul>
            <li>14 Rosewood Avenue, Portland OR</li>
            <li><GlobalPhone link /></li>
            <li><GlobalEmail link /></li>
          </ul>
        </div>
      </div>
      <div class="fine">
        <span data-olx-field="caption">{{ oluxCms.t('Caption', oluxFb['Caption']) }}</span>
        <span data-olx-field="captionB">{{ oluxCms.t('Caption B', oluxFb['Caption B']) }}</span>
      </div>
    </div>
  </footer>
</template>
