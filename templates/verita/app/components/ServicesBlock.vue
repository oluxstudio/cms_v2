<script setup lang="ts">
const olux = useOluxContent('services')
const oluxFb: Record<string, string> = {"Text":"What we do","Headline":"Services built around outcomes","Text B":"Every engagement ends with a deliverable you own \u2014 no decks that gather dust."}
const services = olux.items('Service', {"Title":"title","Text":"text"}, [
  { title: 'Strategy Sprints', text: 'Two-week engagements that turn a fuzzy goal into a costed, sequenced plan.' },
  { title: 'Operations Review', text: 'We map your processes, find the bottlenecks and fix the three that matter.' },
  { title: 'Finance Foundations', text: 'Budgets, forecasts and reporting your board will actually read.' },
], {})
</script>

<template>
  <section class="services" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text B', oluxFb['Text B']) }}</p>
      </div>
      <div class="grid-3">
        <div v-for="s in services" :key="s.title" class="card">
          <h3>{{ s.title }}</h3>
          <p>{{ s.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
