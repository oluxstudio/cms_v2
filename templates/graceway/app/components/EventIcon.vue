<script setup lang="ts">
// Line icons for event cards and the event page (the site's fonts carry no
// emoji, so those render as empty boxes). Inherit the text colour.
export type EventIconName = 'calendar' | 'clock' | 'pin' | 'ticket' | 'users' | 'mic' | 'star' | 'tag'
  | 'arrow' | 'share' | 'calendar-plus' | 'directions' | 'image' | 'check' | 'mail'

defineProps<{ name: EventIconName, size?: number | string }>()
</script>

<template>
  <svg
    class="event-icon" :width="size ?? 20" :height="size ?? 20" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
  >
    <template v-if="name === 'calendar' || name === 'calendar-plus'">
      <rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" />
      <path v-if="name === 'calendar-plus'" d="M12 13v6M9 16h6" />
    </template>
    <template v-else-if="name === 'clock'"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></template>
    <template v-else-if="name === 'pin'"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></template>
    <template v-else-if="name === 'ticket'">
      <path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z" /><path d="M14 6v12" stroke-dasharray="2 2" />
    </template>
    <template v-else-if="name === 'users'">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
    </template>
    <template v-else-if="name === 'mic'">
      <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" /><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8" />
    </template>
    <polygon v-else-if="name === 'star'" points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
    <template v-else-if="name === 'tag'"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" /><path d="M7 7h.01" /></template>
    <path v-else-if="name === 'arrow'" d="M5 12h14M12 5l7 7-7 7" />
    <template v-else-if="name === 'share'">
      <circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98" />
    </template>
    <polygon v-else-if="name === 'directions'" points="3 11 22 2 13 21 11 13 3 11" />
    <template v-else-if="name === 'image'"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></template>
    <path v-else-if="name === 'check'" d="M20 6L9 17l-5-5" />
    <template v-else-if="name === 'mail'"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="M22 6l-10 7L2 6" /></template>
  </svg>
</template>

<style scoped>
.event-icon { flex: none; display: block; }
</style>
