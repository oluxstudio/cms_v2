<script setup lang="ts">
const olux = useOluxContent('faq')
const oluxFb: Record<string, string> = {"Text":"Questions","Headline":"Frequently asked questions"}
const faqs = olux.items('Faq', {"Question":"question","Answer":"answer"}, [
  { question: 'How do engagements start?', answer: 'With a free 45-minute scoping call. You leave with a written brief whether or not we work together.' },
  { question: 'Do you work with early-stage companies?', answer: 'Yes — about half our clients are pre-Series A. Pricing scales with company size.' },
  { question: 'What does an engagement cost?', answer: 'Strategy sprints are fixed-fee. Reviews and retainers are quoted after the scoping call.' },
], {})
</script>

<template>
  <section class="faq" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
      </div>
      <div class="faq-list">
        <div v-for="f in faqs" :key="f.question" class="faq-item">
          <h3>{{ f.question }}</h3>
          <p>{{ f.answer }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
