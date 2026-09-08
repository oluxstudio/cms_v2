<script setup lang="ts">
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "team" collection overrides these rows.
const fallbackMembers = [
  { img: '/assets/images/team-1.jpg', name: 'Amara Ellis', role: 'Creative Director' },
  { img: '/assets/images/team-2.jpg', name: 'Rosa Delgado', role: 'Color Specialist' },
  { img: '/assets/images/team-3.jpg', name: 'Maya Chen', role: 'Curl Expert' },
  { img: '/assets/images/team-4.jpg', name: 'Jules Baptiste', role: 'Senior Stylist' },
]
const members = computed(() => items('team', fallbackMembers))
</script>

<template>
  <section class="team">
    <div class="container">
      <div class="section-head centered" data-olx-key="teamIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="caption">{{ field('teamIntro', 'caption', 'Meet the stylists') }}</p>
        <h2 data-olx-field="heading">{{ field('teamIntro', 'heading', 'Our Collective') }}</h2>
        <p data-olx-field="body">{{ field('teamIntro', 'body', 'Four specialists, one standard — hair that leaves the chair healthier than it arrived.') }}</p>
      </div>
      <div class="team-grid" data-olx-key="team" data-olx-kind="collection">
        <div v-for="m in members" :key="m.name" class="member" data-olx-item>
          <div class="photo"><img :src="m.img || m.image || '/assets/images/team-1.jpg'" :alt="m.name" data-olx-field="image"></div>
          <h3 data-olx-field="name">{{ m.name }}</h3>
          <p data-olx-field="role">{{ m.role }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
