<script setup lang="ts">
const olux = useOluxContent('services')
const oluxFb: Record<string, string> = {"Text":"Our services","Headline":"What we provide to our customers","Text B":"Every treatment starts with a consultation \u2014 your hair, your goals, our craft."}
const services = olux.items('Service', {"Icon":"icon","Title":"title","Text":"text"}, [
  { icon: '✂', title: 'Hairstyles', text: 'Signature cuts and styling shaped to your face, texture and daily routine.' },
  { icon: '🎨', title: 'Coloring', text: 'Balayage, gloss and full color with gentle, salon-grade pigments.' },
  { icon: '🌀', title: 'Hair Curly', text: 'Curl definition, perms and care plans that keep every coil bouncy.' },
  { icon: '✨', title: 'Lamination', text: 'Glass-shine lamination that seals, smooths and protects for weeks.' },
], {})
</script>

<template>
  <section class="services" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="section-head centered">
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text B', oluxFb['Text B']) }}</p>
      </div>
      <div class="services-grid">
        <div v-for="s in services" :key="s.title" class="service-card">
          <div class="icon">{{ s.icon }}</div>
          <h3>{{ s.title }}</h3>
          <p>{{ s.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
