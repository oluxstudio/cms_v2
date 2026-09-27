<script setup lang="ts">
const oluxCms = useOluxContent('community-care-overview')
const oluxFb: Record<string, string> = {}
const programmes = useOutreach()
const { profile, communityCare: cc } = useSiteContent()
</script>

<template>
  <div v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">

    <!-- need help now? — for people in need, not just supporters -->
    <section class="oc-help-strip">
      <div class="container">
        <p><b data-olx-field="helpLead">{{ oluxCms.t('Help Lead', cc.helpLead) }}</b> {{ oluxCms.t('Help Pre', cc.helpPre) }} <a :href="profile.phoneHref">{{ profile.phone }}</a>. {{ oluxCms.t('Help Post', cc.helpPost) }}</p>
      </div>
    </section>

    <!-- programme cards -->
    <section class="oc-programmes">
      <div class="container">
        <div class="section-head">
          <p class="eyebrow" data-olx-field="programmesEyebrow">{{ oluxCms.t('Programmes Eyebrow', cc.programmesEyebrow) }}</p>
          <h2 data-olx-field="programmesTitle">{{ oluxCms.t('Programmes Title', cc.programmesTitle) }}</h2>
        </div>
        <div class="oc-grid" data-olx-panel="outreach-programmes">
          <NuxtLink data-olx-item v-for="p in programmes" :key="p.slug" class="oc-card" :to="`/community-care/${p.slug}`">
            <img :src="p.img" :alt="p.name">
            <div class="body">
              <h3>{{ p.name }}</h3>
              <p>{{ p.short }}</p>
              <span class="meta">🗓 {{ p.schedule }}</span>
              <span class="more">Learn More →</span>
            </div>
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- impact numbers -->
    <section class="oc-impact">
      <div class="container">
        <div class="broadcast-stats" data-olx-panel="care-impact">
          <div data-olx-item v-for="i in cc.impact" :key="i.label"><b>{{ i.value }}</b><span>{{ i.label }}</span></div>
        </div>
      </div>
    </section>

    <!-- story highlight -->
    <section class="oc-story">
      <div class="container">
        <figure class="oc-story-card">
          <img :src="oluxCms.t('Story Img', cc.story.img)" data-olx-field="storyImg" :alt="oluxCms.t('Story Alt', cc.story.alt)">
          <blockquote data-olx-field="storyQuote">
            {{ oluxCms.t('Story Quote', cc.story.quote) }}
            <cite data-olx-field="storyCite">{{ oluxCms.t('Story Cite', cc.story.cite) }}</cite>
          </blockquote>
        </figure>
      </div>
    </section>

    <!-- get involved -->
    <section class="oc-involve">
      <div class="container">
        <div class="section-head center">
          <p class="eyebrow" data-olx-field="involveEyebrow">{{ oluxCms.t('Involve Eyebrow', cc.involveEyebrow) }}</p>
          <h2 data-olx-field="involveTitle">{{ oluxCms.t('Involve Title', cc.involveTitle) }}</h2>
        </div>
        <div class="grid-3" data-olx-panel="care-involvement">
          <div data-olx-item v-for="w in cc.involve" :key="w.title" class="card"><div class="icon">{{ w.icon }}</div><h3>{{ w.title }}</h3><p>{{ w.text }}</p><NuxtLink class="btn ghost" :to="w.cta.to">{{ w.cta.label }}</NuxtLink></div>
        </div>
      </div>
    </section>
  </div>
</template>
