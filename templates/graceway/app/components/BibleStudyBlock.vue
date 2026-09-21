<script setup lang="ts">
const groups = useStudyGroups()
// section copy, chips and image come from the global data source
const { bibleStudy } = useSiteContent()
</script>

<template>
  <section id="bible-study" class="bible-study study">
    <div class="container">
      <div class="study-top">

        <div class="study-photo">
          <span class="study-arch" aria-hidden="true"></span>
          <img :src="bibleStudy.img.src" :alt="bibleStudy.img.alt">
          <span v-for="(chip, i) in bibleStudy.chips" :key="chip.label" class="study-chip" :class="`c${i + 1}`">{{ chip.icon }} {{ chip.label }} <b>{{ chip.value }}</b></span>
          <svg class="study-spark s1" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0q2 8 12 12-10 4-12 12-2-8-12-12 10-4 12-12z" fill="currentColor"/></svg>
          <svg class="study-spark s2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0q2 8 12 12-10 4-12 12-2-8-12-12 10-4 12-12z" fill="currentColor"/></svg>
        </div>
        <div class="study-copy">
          <h2 class="mega">
            <template v-for="(line, i) in contentLines(bibleStudy.title)" :key="i">
              <br v-if="i">{{ line }}
            </template>
          </h2>
          <p class="sub">{{ bibleStudy.sub }}</p>
          <CtaButton :to="bibleStudy.cta.to" :label="bibleStudy.cta.label" />
          <p class="study-note">{{ bibleStudy.note }}</p>
        </div>
      </div>

      <!-- stats row: each item opens that group's page -->
      <div class="study-stats">
        <NuxtLink v-for="g in groups" :key="g.slug" class="study-stat">
          <b>{{ g.stat }}</b>
          <span>{{ g.icon }} {{ g.title }}</span>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>
