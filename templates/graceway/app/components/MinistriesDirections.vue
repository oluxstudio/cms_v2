<script setup lang="ts">
withDefaults(defineProps<{ grid?: boolean }>(), { grid: false })

const directions = [
  { to: '/community-care', color: 'd-green', img: '/assets/images/event-1.jpg', tag: 'Open to all', title: 'Community Care', meta: 'Serving every Saturday' },
  { to: '/bible-study', color: 'd-orange', img: '/assets/images/circle-2.jpg', tag: 'Beginner friendly', title: 'Bible Study', meta: 'Midweek home groups' },
  { to: '/worship', color: 'd-blue', img: '/assets/images/event-2.jpg', tag: 'Open to all', title: 'Worship & Music', meta: 'Rehearsals every Thursday' },
  { to: '/youth', color: 'd-green', img: '/assets/images/circle-3.jpg', tag: 'Teens 13–19', title: 'Youth Ministry', meta: 'Fridays in the Youth Hall' },
  { to: '/kids', color: 'd-orange', img: '/assets/images/event-3.jpg', tag: 'Families', title: 'Kids Church', meta: 'Both Sunday services' },
  { to: '/prayer', color: 'd-blue', img: '/assets/images/bible-study.jpg', tag: 'Early birds', title: 'Prayer Watch', meta: 'Wednesdays 6:00 AM' },
]
</script>

<template>
  <section class="directions">
    <div class="container">
      <div class="directions-head">
        <div class="directions-intro">
          <span class="star-badge">✳</span>
          <p>At CAC Blackburn we offer many pathways to grow and serve — from worship and study to caring for our neighbours across the city.</p>
        </div>
        <h2 class="directions-title">Directions<br>of ministry</h2>
        <svg class="directions-squiggle" viewBox="0 0 200 260" aria-hidden="true">
          <path d="M150 10 q60 60 -10 120 q-70 60 20 120 M180 30 q40 70 -30 130" fill="none" stroke="#d9e021" stroke-width="3"/>
        </svg>
      </div>

      <div class="directions-row" :class="{ grid }">
        <NuxtLink v-for="(d, i) in directions" :key="d.title" class="dcard" :class="[d.color, `layout-${i % 2 ? 'b' : 'a'}`]" :to="d.to">
          <span class="dcard-arrow"><svg viewBox="0 0 24 24"><path d="M7 17 17 7M9 7h8v8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <img class="dcard-img" :src="d.img" :alt="d.title">
          <div class="dcard-info">
            <span class="dcard-tag">{{ d.tag }}</span>
            <h3>{{ d.title }}</h3>
            <p>{{ d.meta }}</p>
          </div>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>
