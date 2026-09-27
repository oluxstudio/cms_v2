<script setup lang="ts">
const notices = [
  { title: 'Harvest supper', text: 'Bring a dish to share this Friday evening in the hall.' },
  { title: 'Choir practice moved', text: 'Thursday rehearsal starts at 7:30 PM this week only.' },
  { title: 'Car park closure', text: 'The rear car park is closed on Saturday for resurfacing.' },
]
</script>
<template>
  <section><article v-for="n in notices" :key="n.title"><h3>{{ n.title }}</h3><p>{{ n.text }}</p></article></section>
</template>
