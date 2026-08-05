<script setup lang="ts">
const olux = useOluxContent('team')
const oluxFb: Record<string, string> = {"Text":"Meet the stylists","Headline":"Our Collective","Text B":"Four specialists, one standard \u2014 hair that leaves the chair healthier than it arrived."}
const members = olux.items('Member', {"Image":"img","Name":"name","Role":"role"}, [
  { img: '/assets/images/team-1.svg', name: 'Amara Ellis', role: 'Creative Director' },
  { img: '/assets/images/team-2.svg', name: 'Rosa Delgado', role: 'Color Specialist' },
  { img: '/assets/images/team-3.svg', name: 'Maya Chen', role: 'Curl Expert' },
  { img: '/assets/images/team-4.svg', name: 'Jules Baptiste', role: 'Senior Stylist' },
], {})
</script>

<template>
  <section class="team" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div class="section-head centered">
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <p>{{ olux.t('Text B', oluxFb['Text B']) }}</p>
      </div>
      <div class="team-grid">
        <div v-for="m in members" :key="m.name" class="member">
          <div class="photo"><img :src="m.img" :alt="m.name"></div>
          <h3>{{ m.name }}</h3>
          <p>{{ m.role }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
