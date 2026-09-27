<script setup lang="ts">
const oluxCms = useOluxContent('pastors')
const oluxFb: Record<string, string> = {}
const props = withDefaults(defineProps<{
  /** show only the first N members (0 = all) */
  limit?: number
  /** show the "View All Members" button linking to the About page section */
  showViewAll?: boolean
}>(), {
  limit: 0,
  showViewAll: false,
})

const members = useMembers()
// section copy comes from the global data source
const { pastorsHead } = useSiteContent()
const shown = computed(() => props.limit > 0 ? members.slice(0, props.limit) : members)
</script>

<template>
  <section id="leadership" class="leaderships" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head center">
        <p class="eyebrow" data-olx-field="eyebrow">{{ oluxCms.t('Eyebrow', pastorsHead.eyebrow) }}</p>
        <h2 data-olx-field="title">{{ oluxCms.t('Title', pastorsHead.title) }}</h2>
        <p data-olx-field="text">{{ oluxCms.t('Text', pastorsHead.text) }}</p>
      </div>
      <div class="team-flex" data-olx-panel="leadership">
        <NuxtLink data-olx-item v-for="p in shown" :key="p.slug" class="member" :to="`/leadership/${p.slug}`">
          <img :src="p.img" :alt="`Portrait of ${p.name}`">
          <h3>{{ p.name }}</h3>
          <p>{{ p.role }}</p>
        </NuxtLink>
      </div>
      <div v-if="showViewAll" class="team-more">
        <CtaButton :to="oluxCms.t('Cta To', pastorsHead.cta.to)" :label="oluxCms.t('Cta Label', pastorsHead.cta.label)" data-olx-field="ctaLabel" />
      </div>
    </div>
  </section>
</template>
