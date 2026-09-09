<script setup lang="ts">
const oluxCms = useOluxContent('pricing')
const oluxFb: Record<string, string> = {}
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "pricing" collection overrides these rows.
const fallbackPrices = oluxCms.items('Fallback Price', {"Title":"title","Description":"desc","Price":"price"}, [
  { title: 'Signature Cut & Style', desc: 'Consultation, wash, precision cut and finish.', price: '$45' },
  { title: 'Full Color', desc: 'Single-process color with gloss and blowout.', price: '$66' },
  { title: 'Balayage / Highlights', desc: 'Hand-painted dimension with toner.', price: '$58' },
  { title: 'Curl Definition', desc: 'Hydration, shaping and diffuse styling for curls.', price: '$49' },
  { title: 'Lamination', desc: 'Glass-shine treatment that smooths for weeks.', price: '$52' },
  { title: 'Express Blowout', desc: 'Wash, blow-dry and quick finish.', price: '$35' },
], {})
const prices = computed(() => items('pricing', fallbackPrices))
</script>

<template>
  <section id="pricing" class="pricing" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head centered" data-olx-key="pricingIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="caption">{{ field('pricingIntro', 'caption', 'The best pricing') }}</p>
        <h2 data-olx-field="heading">{{ field('pricingIntro', 'heading', 'A menu for every hair day') }}</h2>
        <p data-olx-field="body">{{ field('pricingIntro', 'body', 'Transparent prices, no surprise add-ons — longer or thicker hair may add a little time, never stress.') }}</p>
      </div>
      <div class="price-list" data-olx-key="pricing" data-olx-kind="collection">
        <div v-for="p in prices" :key="p.title" class="price-row" data-olx-item>
          <div class="price-item">
            <h3 data-olx-field="title">{{ p.title }}</h3>
            <p data-olx-field="desc">{{ p.desc }}</p>
          </div>
          <div class="dots"></div>
          <div class="price" data-olx-field="price">{{ p.price }}</div>
        </div>
      </div>
    </div>
  </section>
</template>
