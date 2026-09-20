<script setup>
/** @olux-collection Team */
const members = [
  { name: 'Pastor One', role: 'Lead Pastor', img: '/assets/images/p1.jpg', joined: '2020-01-05' },
  { name: 'Pastor Two', role: 'Youth Pastor', img: '/assets/images/p2.jpg', joined: '2021-03-10' },
  { name: 'Pastor Three', role: 'Worship', img: '/assets/images/p3.jpg', joined: '2022-06-01' },
]
</script>
<template>
  <section><div v-for="m in members" :key="m.name">{{ m.name }}</div></section>
</template>
