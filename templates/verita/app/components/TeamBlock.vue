<script setup lang="ts">
const olux = useOluxContent('team')
const oluxFb: Record<string, string> = {"Text":"Who you work with","Headline":"Senior people on every engagement"}
const members = olux.items('Member', {"Name":"name","Role":"role","Image":"img"}, [
  { name: 'Amara Osei', role: 'Managing Partner', img: '/assets/images/avatar1.svg' },
  { name: 'Jonas Keller', role: 'Head of Strategy', img: '/assets/images/avatar2.svg' },
  { name: 'Priya Nair', role: 'Finance Lead', img: '/assets/images/avatar3.svg' },
], {})
</script>

<template>
  <section class="team" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
      </div>
      <div class="team-grid">
        <div v-for="m in members" :key="m.name" class="member">
          <img :src="m.img" :alt="m.name">
          <h3>{{ m.name }}</h3>
          <p>{{ m.role }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
