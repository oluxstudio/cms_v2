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
        <p class="eyebrow">{{ pastorsHead.eyebrow }}</p>
        <h2>{{ pastorsHead.title }}</h2>
        <p>{{ pastorsHead.text }}</p>
      </div>
      <div class="team-flex">
        <NuxtLink v-for="p in shown" :key="p.slug" class="member" :to="`/leadership/${p.slug}`">
          <img :src="p.img" :alt="`Portrait of ${p.name}`">
          <h3>{{ p.name }}</h3>
          <p>{{ p.role }}</p>
        </NuxtLink>
      </div>
      <div v-if="showViewAll" class="team-more">
        <CtaButton :to="pastorsHead.cta.to" :label="pastorsHead.cta.label" />
      </div>
    </div>
  </section>
</template>
