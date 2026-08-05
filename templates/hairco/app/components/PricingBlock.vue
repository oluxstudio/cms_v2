<script setup lang="ts">
const olux = useOluxContent('pricing')
const oluxFb: Record<string, string> = {"Text":"The best pricing","Headline":"A menu for every hair day","Text B":"Transparent prices, no surprise add-ons \u2014 longer or thicker hair may add a little time, never stress."}
const prices = olux.items('Price', {"Title":"title","Description":"desc","Price":"price"}, [
  { title: 'Signature Cut & Style', desc: 'Consultation, wash, precision cut and finish.', price: '$45' },
  { title: 'Full Color', desc: 'Single-process color with gloss and blowout.', price: '$66' },
  { title: 'Balayage / Highlights', desc: 'Hand-painted dimension with toner.', price: '$58' },
  { title: 'Curl Definition', desc: 'Hydration, shaping and diffuse styling for curls.', price: '$49' },
  { title: 'Lamination', desc: 'Glass-shine treatment that smooths for weeks.', price: '$52' },
  { title: 'Express Blowout', desc: 'Wash, blow-dry and quick finish.', price: '$35' },
], {})
</script>

<template>
  <section class="pricing" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="section-head centered">
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text B', oluxFb['Text B']) }}</p>
      </div>
      <div class="price-list">
        <div v-for="p in prices" :key="p.title" class="price-row">
          <div class="price-item">
            <h3>{{ p.title }}</h3>
            <p>{{ p.desc }}</p>
          </div>
          <div class="dots"></div>
          <div class="price">{{ p.price }}</div>
        </div>
      </div>
    </div>
  </section>
</template>
