<script setup lang="ts">
const oluxCms = useOluxContent('site-footer')
const oluxFb: Record<string, string> = {"Subheadline":"Explore","Subheadline B":"Services","Subheadline C":"Contact","Caption":"\u00a9 Hair Co. All rights reserved.","Caption B":"Built with Olux Studio"}
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallbacks — the CMS "Footer Links" / "Footer Services" collections override these rows.
const fallbackLinks = oluxCms.items('Fallback Link', {"Label":"label","Href":"href"}, [
  { label: 'About Us', href: '/about' },
  { label: 'Services', href: '/services' },
  { label: 'Appointment', href: '/appointment' },
  { label: 'Contact Us', href: '/contact-us' },
], {})
const links = computed(() => items('footerLinks', fallbackLinks))

const fallbackServices = oluxCms.items('Fallback Service', {"Label":"label","Href":"href"}, [
  { label: 'Hairstyles', href: '/services' },
  { label: 'Coloring', href: '/services' },
  { label: 'Hair Curly', href: '/services' },
  { label: 'Lamination', href: '/services' },
], {})
const services = computed(() => items('footerServices', fallbackServices))
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
          <ul data-olx-key="footerLinks" data-olx-kind="collection">
            <li v-for="l in links" :key="l.label" data-olx-item>
              <NuxtLink :to="l.href" data-olx-field="href"><span data-olx-field="label">{{ l.label }}</span></NuxtLink>
            </li>
          </ul>
        </div>
        <div>
          <h4 data-olx-field="subheadlineB">{{ oluxCms.t('Subheadline B', oluxFb['Subheadline B']) }}</h4>
          <ul data-olx-key="footerServices" data-olx-kind="collection">
            <li v-for="s in services" :key="s.label" data-olx-item>
              <NuxtLink :to="s.href" data-olx-field="href"><span data-olx-field="label">{{ s.label }}</span></NuxtLink>
            </li>
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
