<script setup lang="ts">
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "testimonials" collection overrides these rows.
const fallbackReviews = [
  { quote: 'The consultation alone was worth the visit — my color has never looked this natural.', name: 'Priya N.', role: 'Coloring client' },
  { quote: 'Three salons gave up on my curls. Hair Co. gave me a routine that actually works.', name: 'Dana W.', role: 'Curl care client' },
  { quote: 'Booked at noon, transformed by three. Effortless from start to finish.', name: 'Elena M.', role: 'Styling client' },
]
const reviews = computed(() => items('testimonials', fallbackReviews))
</script>

<template>
  <section class="testimonials tinted">
    <div class="container">
      <div class="section-head centered" data-olx-key="testimonialsIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="caption">{{ field('testimonialsIntro', 'caption', 'Client review') }}</p>
        <h2 data-olx-field="heading">{{ field('testimonialsIntro', 'heading', 'Loved by the people in our chairs') }}</h2>
      </div>
      <div class="reviews-grid" data-olx-key="testimonials" data-olx-kind="collection">
        <div v-for="r in reviews" :key="r.name" class="review" data-olx-item>
          <div class="stars">★★★★★</div>
          <blockquote data-olx-field="quote">{{ r.quote }}</blockquote>
          <div class="who"><span data-olx-field="name">{{ r.name }}</span><span data-olx-field="role">{{ r.role }}</span></div>
        </div>
      </div>
    </div>
  </section>
</template>
