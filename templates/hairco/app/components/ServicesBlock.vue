<script setup lang="ts">
const oluxCms = useOluxContent('services')
const oluxFb: Record<string, string> = {}
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "services" collection overrides these rows.
const fallbackServices = oluxCms.items('Fallback Service', {"Icon":"icon","Title":"title","Text":"text"}, [
  { icon: '✂', title: 'Hairstyles', text: 'Signature cuts and styling shaped to your face, texture and daily routine.' },
  { icon: '🎨', title: 'Coloring', text: 'Balayage, gloss and full color with gentle, salon-grade pigments.' },
  { icon: '🌀', title: 'Hair Curly', text: 'Curl definition, perms and care plans that keep every coil bouncy.' },
  { icon: '✨', title: 'Lamination', text: 'Glass-shine lamination that seals, smooths and protects for weeks.' },
], {})
const services = computed(() => items('services', fallbackServices))
</script>

<template>
  <section class="services" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head centered" data-olx-key="servicesIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="caption">{{ field('servicesIntro', 'caption', 'Our services') }}</p>
        <h2 data-olx-field="heading">{{ field('servicesIntro', 'heading', 'What we provide to our customers') }}</h2>
        <p data-olx-field="body">{{ field('servicesIntro', 'body', 'Every treatment starts with a consultation — your hair, your goals, our craft.') }}</p>
      </div>
      <div class="services-grid" data-olx-key="services" data-olx-kind="collection">
        <div v-for="s in services" :key="s.title" class="service-card" data-olx-item>
          <div class="icon" data-olx-field="icon">{{ s.icon || '✨' }}</div>
          <h3 data-olx-field="title">{{ s.title }}</h3>
          <p data-olx-field="text">{{ s.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
