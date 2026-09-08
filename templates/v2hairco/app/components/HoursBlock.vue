<script setup lang="ts">
import { computed } from 'vue'

const { field, items } = useCms()

// Handcoded fallback — the CMS "Working Hours" collection overrides these rows.
const fallbackHours = [
  { title: 'Monday — Friday', text: '9:00 — 20:00' },
  { title: 'Saturday', text: '10:00 — 18:00' },
  { title: 'Sunday', text: 'Closed' },
]
const hours = computed(() => items('workingHours', fallbackHours))
</script>

<template>
  <section class="hours">
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
        <a class="btn" href="/appointment">Book an appointment</a>
      </div>
      <div class="visual">
        <img src="/assets/images/hours.jpg" alt="Salon interior">
      </div>
    </div>
  </section>
</template>
