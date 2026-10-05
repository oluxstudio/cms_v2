<script setup lang="ts">
/** "Join the church" CTA card at the foot of the broadcast channels section. */
// copy is bound by the parent block (BroadcastChannels) so it stays CMS-editable there
defineProps<{ title: string; text: string; ctaLabel: string; ctaTo: string }>()
</script>

<template>
  <div class="bcx-join">
    <div>
      <h2>{{ title }}</h2>
      <p>{{ text }}</p>
    </div>
    <NuxtLink class="btn bcx-join-btn" :to="ctaTo">{{ ctaLabel }} <span class="arrow">↗</span></NuxtLink>
  </div>
</template>

<style scoped>
.bcx-join { margin-top: 2.4rem; background: var(--color-secondary); border-radius: 26px; padding: 2.4rem;
  display: flex; align-items: center; justify-content: space-between; gap: 2rem;
  box-shadow: 0 14px 32px rgba(20, 24, 29, .12); }
.bcx-join h2 { color: #fff; }
.bcx-join p { font-size: 17px; color: #d5dce2; margin-top: .5rem; max-width: 480px; }
.bcx-join-btn { flex: none; }

@media (max-width: 820px) {
  .bcx-join { flex-direction: column; text-align: center; }
}
</style>
