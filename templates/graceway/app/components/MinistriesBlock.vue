<script setup lang="ts">
const oluxCms = useOluxContent('ministries')
const oluxFb: Record<string, string> = {}
const { ministriesBento: ministries } = useSiteContent()
</script>

<template>
  <section class="ministries" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="ministry-bento">
        <NuxtLink v-for="m in ministries" :key="m.title" class="mcard" :class="[m.color, m.size]" :to="m.to ?? '/ministries'">
          <div class="mcard-top">
            <span class="mcard-tag">{{ m.tag }}</span>
            <span class="mcard-badge">{{ m.icon }}</span>
          </div>
          <div class="mcard-body">
            <h3>{{ m.title }}</h3>
            <p>{{ m.text }}</p>
          </div>
          <div class="mcard-meta">
            <span>👥 {{ m.members }}</span>
            <span>🗓 {{ m.meets }}</span>
          </div>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>
