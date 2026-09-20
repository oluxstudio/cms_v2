<script setup lang="ts">
/** @olux-collection Ministries */
const ministries = [
  {
    icon: '🙌', name: 'Worship & Music',
    text: 'Our worship teams lead the congregation every Sunday with choir, band and production. Whether you sing, play an instrument or love the tech booth, there is a seat for you.',
    leader: 'Grace Lindqvist', meets: 'Rehearsals — Thursdays 7:00 PM',
  },
  {
    icon: '👶', name: 'Children\'s Ministry',
    text: 'A safe, joyful environment where kids from nursery to grade 6 discover faith through stories, songs and play while parents worship.',
    leader: 'Naomi Carter', meets: 'Sundays during both services',
  },
  {
    icon: '🔥', name: 'Youth Ministry',
    text: 'Teens gather for games, honest conversation and Bible study. A place to ask big questions and build friendships that last.',
    leader: 'Samuel Reyes', meets: 'Fridays 6:30 PM — Youth Hall',
  },
  {
    icon: '🤝', name: 'Outreach & Missions',
    text: 'From the neighbourhood food pantry to mission trips abroad, we serve practical needs and share hope beyond our walls.',
    leader: 'Rev. Daniel Okafor', meets: 'Food pantry — Saturdays 9:00 AM',
  },
  {
    icon: '🏠', name: 'Small Groups',
    text: 'Midweek home groups across the city — share a meal, study scripture and do life together in a circle small enough to know your name.',
    leader: 'Ruth & Peter Alonso', meets: 'Weeknights — various homes',
  },
  {
    icon: '💒', name: 'Prayer Ministry',
    text: 'Intercessors who pray for the church, the city and every request left in our prayer box. Join the early morning watch or pray from home.',
    leader: 'Esther Mwangi', meets: 'Wednesdays 6:00 AM — Chapel',
  },
]
const rows = computed(() => useCms().items('ministries', ministries))
</script>

<template>
  <section class="ministry-list">
    <div class="container">
      <div class="ministry-grid">
        <article v-for="m in rows" :key="m.name" class="ministry-card">
          <div class="icon">{{ m.icon }}</div>
          <h3>{{ m.name }}</h3>
          <p>{{ m.text }}</p>
          <ul class="facts">
            <li><b>Leader:</b> {{ m.leader }}</li>
            <li><b>Meets:</b> {{ m.meets }}</li>
          </ul>
          <NuxtLink class="btn ghost" to="/contact">Join This Ministry</NuxtLink>
        </article>
      </div>
    </div>
  </section>
</template>
