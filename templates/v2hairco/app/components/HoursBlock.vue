<script setup lang="ts">
const oluxCms = useOluxContent('hours')
const oluxFb: Record<string, string> = {"CTA Label":"Book an appointment","CTA Link":"/appointment","Image":"/assets/images/hours.jpg"}
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "Working Hours" collection overrides these rows.
const fallbackHours = oluxCms.items('Fallback Hour', {"Title":"title","Text":"text"}, [
  { title: 'Monday — Friday', text: '9:00 — 20:00' },
  { title: 'Saturday', text: '10:00 — 18:00' },
  { title: 'Sunday', text: 'Closed' },
], {})
const hours = computed(() => items('workingHours', fallbackHours))
</script>

<template>
  <section class="hours" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="info">
        <div data-olx-key="hoursIntro" data-olx-kind="component">
          <h2 data-olx-field="heading">{{ field('hoursIntro', 'heading', 'Working Hours') }}</h2>
        </div>
        <ul data-olx-key="workingHours" data-olx-kind="collection">
          <li v-for="h in hours" :key="h.title" data-olx-item>
            <span data-olx-field="title">{{ h.title }}</span><span class="dots" /><span data-olx-field="text">{{ h.text }}</span>
          </li>
        </ul>
        <div data-olx-key="hoursContact" data-olx-kind="component">
          <h2 data-olx-field="appointmentsHeading">{{ field('hoursContact', 'appointmentsHeading', 'Appointments') }}</h2>
        </div>
        <div class="reach">
          <b><GlobalPhone link /></b>
          <b><GlobalEmail link /></b>
        </div>
        <a data-olx-field="ctaLabel" class="btn" :href="oluxCms.t('CTA Link', oluxFb['CTA Link'])">{{ oluxCms.t('CTA Label', oluxFb['CTA Label']) }}</a>
      </div>
      <div class="visual">
        <img data-olx-field="image" :src="oluxCms.t('Image', oluxFb['Image'])" alt="Salon interior">
      </div>
    </div>
  </section>
</template>
