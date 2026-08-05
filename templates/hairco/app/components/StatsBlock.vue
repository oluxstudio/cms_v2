<script setup lang="ts">
const olux = useOluxContent('stats')
const oluxFb: Record<string, string> = {"Text":"Hair Co. in numbers","Headline":"Trusted well beyond our own chairs"}
const stats = olux.items('Stat', {"Title":"title","Text":"text"}, [
  { title: '30+', text: 'International Branches' },
  { title: '12k', text: 'Happy Clients World Wide' },
  { title: '15', text: 'Years of In-Field Experience' },
  { title: '98%', text: 'Positive Client Reviews' },
], {})
</script>

<template>
  <section class="stats" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="section-head centered" style="margin-bottom:2.6rem">
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2 style="color:#fff">{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
      </div>
      <div class="stats-grid">
        <div v-for="s in stats" :key="s.text" class="stat">
          <h3><b>{{ s.title }}</b></h3>
          <p>{{ s.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
