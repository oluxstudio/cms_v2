<script setup lang="ts">
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "services" collection overrides these rows.
const fallbackServices = [
  { icon: '✂', title: 'Hairstyles', text: 'Signature cuts and styling shaped to your face, texture and daily routine.' },
  { icon: '🎨', title: 'Coloring', text: 'Balayage, gloss and full color with gentle, salon-grade pigments.' },
  { icon: '🌀', title: 'Hair Curly', text: 'Curl definition, perms and care plans that keep every coil bouncy.' },
  { icon: '✨', title: 'Lamination', text: 'Glass-shine lamination that seals, smooths and protects for weeks.' },
]
const services = computed(() => items('services', fallbackServices))
</script>

<template>
  <section class="services">
    <div class="container">
      <div>
        <div class="section-head" data-olx-key="servicesIntro" data-olx-kind="component">
          <p class="eyebrow" data-olx-field="caption">{{ field('servicesIntro', 'caption', 'Our services') }}</p>
          <h2 data-olx-field="heading">{{ field('servicesIntro', 'heading', 'What We Provide To Our Customers') }}</h2>
          <p data-olx-field="body">{{ field('servicesIntro', 'body', 'Every treatment starts with a consultation — your hair, your goals, our craft.') }}</p>
        </div>
        <div class="services-grid" data-olx-key="services" data-olx-kind="collection">
          <div v-for="s in services" :key="s.title" class="service-card" data-olx-item>
            <div class="icon" data-olx-field="icon">{{ s.icon || '✨' }}</div>
            <div>
              <h3 data-olx-field="title">{{ s.title }}</h3>
              <p data-olx-field="text">{{ s.text }}</p>
            </div>
          </div>
        </div>
      </div>
      <div class="photo-wrap">
        <a class="badge-spin" href="/appointment" aria-label="Book an appointment">
          <svg viewBox="0 0 130 130">
            <defs><path id="circlePath" d="M 65,65 m -47,0 a 47,47 0 1,1 94,0 a 47,47 0 1,1 -94,0" /></defs>
            <text><textPath href="#circlePath">· Book appointment · Book now</textPath></text>
          </svg>
          <span class="play">➜</span>
        </a>
        <img src="/assets/images/services-circle.jpg" alt="Inside the salon">
      </div>
    </div>
  </section>
</template>
