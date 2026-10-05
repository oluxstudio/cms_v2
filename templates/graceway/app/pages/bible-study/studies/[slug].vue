<script setup lang="ts">
const route = useRoute()
const studies = useStudies()
const study = studies.find(s => s.slug === route.params.slug)

if (!study) {
  throw createError({ statusCode: 404, statusMessage: 'Study not found', fatal: true })
}

useHead({ title: `${study.title} — Bible Study — CAC Blackburn` })
const gatewayUrl = `https://www.biblegateway.com/passage/?search=${encodeURIComponent(study.passage)}&version=NIV`
</script>

<template>
  <div v-if="study">
    <SiteHeader />
    <PageHeroContent :crumbs="[{ label: 'Ministries', to: '/ministries' }, { label: 'Bible Study', to: '/bible-study' }, { label: study.title }]">
      <p class="eyebrow">{{ study.series }}<template v-if="study.week"> · {{ study.week }}</template> · {{ study.audience }}</p>
      <h1>{{ study.title }}</h1>
      <p>{{ study.passage }} · {{ study.date.slice(0, 10) }}</p>
    </PageHeroContent>

    <section class="study-view">
      <div class="container">
        <!-- key passage -->
        <div class="sv-passage">
          <blockquote>{{ study.excerpt }}</blockquote>
          <a :href="gatewayUrl" target="_blank" rel="noopener">Read the full passage ({{ study.passage }}) →</a>
        </div>

        <!-- optional teaching recording — MediaPlayer handles local files and
             YouTube / Facebook / TikTok links alike -->
        <MediaPlayer
          v-if="study.videoUrl || study.audioUrl" class="sv-video"
          :src="(study.videoUrl || study.audioUrl)!" :type="study.videoUrl ? undefined : 'audio'"
          :poster="study.poster" :title="study.title" :tags="[study.series, study.audience]" :item="study"
        />

        <div class="sv-body">
          <h2>About this study</h2>
          <p>{{ study.summary }}</p>

          <h2>Discussion questions</h2>
          <ol class="sv-questions">
            <li v-for="q in study.questions" :key="q">{{ q }}</li>
          </ol>

          <div class="sv-takeaway">
            <b>✦ This week's challenge</b>
            <p>{{ study.takeaway }}</p>
          </div>

          <div class="sv-verse">
            <span>Memory verse</span>
            <b>{{ study.memoryVerse }}</b>
          </div>
        </div>

        <div class="study-detail-actions center">
          <NuxtLink class="btn ghost" to="/bible-study">← All Studies</NuxtLink>
          <NuxtLink class="btn" to="/contact">Join a Group</NuxtLink>
        </div>
      </div>
    </section>

    <SiteFooter />
  </div>
</template>
