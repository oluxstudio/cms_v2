<script setup lang="ts">
const route = useRoute()
const groups = useStudyGroups()
const group = groups.find(g => g.slug === route.params.slug)

if (!group) {
  throw createError({ statusCode: 404, statusMessage: 'Group not found', fatal: true })
}

useHead({ title: `${group.title} — Bible Study — CAC Blackburn` })
</script>

<template>
  <div v-if="group">
    <SiteHeader />
    <section class="page-hero">
      <div class="container">
        <BreadCrumbs :items="[{ label: 'Ministries', to: '/ministries' }, { label: 'Bible Study', to: '/bible-study' }, { label: group.title }]" />
        <PageHeroContent>
          <p class="eyebrow">Bible study</p>
          <h1>{{ group.icon }} {{ group.title }}</h1>
          <p>{{ group.statLabel }}</p>
        </PageHeroContent>
      </div>
    </section>

    <section class="study-detail">
      <div class="container">
        <div class="study-detail-grid">
          <img :src="group.img" :alt="group.title">
          <div>
            <h2>About this group</h2>
            <p>{{ group.text }}</p>
            <ul class="study-facts">
              <li><b>🗓 Meets:</b> {{ group.meets }}</li>
              <li><b>📍 Where:</b> {{ group.place }}</li>
              <li><b>🧑‍🏫 Led by:</b> {{ group.leader }}</li>
            </ul>
            <div class="study-detail-actions">
              <NuxtLink class="btn" to="/contact">Join This Group</NuxtLink>
              <NuxtLink class="btn ghost" to="/bible-study">← All Groups</NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </section>

    <DonateCta />
    <SiteFooter />
  </div>
</template>
