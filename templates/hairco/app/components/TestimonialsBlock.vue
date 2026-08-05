<script setup lang="ts">
const olux = useOluxContent('testimonials')
const oluxFb: Record<string, string> = {"Text":"Client review","Headline":"Loved by the people in our chairs"}
const reviews = olux.items('Review', {"Quote":"quote","Name":"name","Role":"role"}, [
  { quote: 'The consultation alone was worth the visit — my color has never looked this natural.', name: 'Priya N.', role: 'Coloring client' },
  { quote: 'Three salons gave up on my curls. Hair Co. gave me a routine that actually works.', name: 'Dana W.', role: 'Curl care client' },
  { quote: 'Booked at noon, transformed by three. Effortless from start to finish.', name: 'Elena M.', role: 'Styling client' },
], {})
</script>

<template>
  <section class="testimonials tinted" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="section-head centered">
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
      </div>
      <div class="reviews-grid">
        <div v-for="r in reviews" :key="r.name" class="review">
          <div class="stars">★★★★★</div>
          <blockquote>{{ r.quote }}</blockquote>
          <div class="who">{{ r.name }}<span>{{ r.role }}</span></div>
        </div>
      </div>
    </div>
  </section>
</template>
