<script setup lang="ts">
const route = useRoute()
const programmes = useOutreach()
const prog = programmes.find(p => p.slug === route.params.slug)

if (!prog) {
  throw createError({ statusCode: 404, statusMessage: 'Programme not found', fatal: true })
}

useHead({ title: `${prog.name} — Community Care — CAC Blackburn` })
</script>

<template>
  <div v-if="prog">
    <SiteHeader />
    <section class="page-hero">
      <div class="container">
        <BreadCrumbs :items="[{ label: 'Ministries', to: '/ministries' }, { label: 'Community Care', to: '/community-care' }, { label: prog.name }]" />
        <PageHeroContent>
          <p class="eyebrow">Community care & outreach</p>
          <h1>{{ prog.name }}</h1>
          <p>{{ prog.short }}</p>
        </PageHeroContent>
      </div>
    </section>

    <section class="oc-detail">
      <div class="container">
        <div class="study-detail-grid">
          <img :src="prog.img" :alt="prog.name">
          <div>
            <h2>What it is</h2>
            <p>{{ prog.full }}</p>
            <ul class="study-facts">
              <li><b>👥 Who it serves:</b> {{ prog.serves }}</li>
              <li><b>🗓 When:</b> {{ prog.schedule }}</li>
              <li><b>📍 Where:</b> {{ prog.location }}</li>
              <li>
                <b>🧑‍🏫 Led by:</b>
                <NuxtLink v-if="prog.coordinatorSlug" :to="`/leadership/${prog.coordinatorSlug}`" class="oc-lead-link">{{ prog.coordinator }}</NuxtLink>
                <template v-else>{{ prog.coordinator }}</template>
              </li>
            </ul>
          </div>
        </div>

        <div class="oc-detail-cols">
          <div class="oc-panel help">
            <h3>💛 Need this help?</h3>
            <p>{{ prog.accessHelp }}</p>
          </div>
          <div class="oc-panel">
            <h3>🙋 Volunteer with us</h3>
            <p>{{ prog.volunteer }}</p>
            <p v-if="prog.requirements" class="oc-req">{{ prog.requirements }}</p>
            <NuxtLink class="btn" to="/contact">I Want to Help</NuxtLink>
          </div>
          <div class="oc-panel">
            <h3>📦 What we need right now</h3>
            <ul class="oc-needs">
              <li v-for="n in prog.needs" :key="n">{{ n }}</li>
            </ul>
            <p v-if="prog.partners.length" class="oc-partners">In partnership with {{ prog.partners.join(' · ') }}</p>
          </div>
        </div>

        <div class="study-detail-actions center">
          <NuxtLink class="btn ghost" to="/community-care">← All Programmes</NuxtLink>
          <NuxtLink class="btn" to="/contact">Get Involved</NuxtLink>
        </div>
      </div>
    </section>

    <SiteFooter />
  </div>
</template>
