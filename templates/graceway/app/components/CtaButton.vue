<script setup lang="ts">
// Shared section CTA — pink pill with a circular arrow badge.
// Used by every home-page section button (the hero keeps its own style).
withDefaults(defineProps<{
  to?: string
  label: string
}>(), {
  to: '/contact',
})
</script>

<template>
  <NuxtLink class="cta-btn" :to="to">
    <span class="cta-label">{{ label }}</span>
    <span class="cta-badge" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
    </span>
  </NuxtLink>
</template>

<style scoped>
.cta-btn {
  display: inline-flex;
  align-items: center;
  gap: .8rem;
  background: var(--color-primary);
  color: #fff;
  font-family: var(--font-heading);
  font-weight: 700;
  font-size: 1.05rem;
  line-height: 1;
  padding: .55rem .55rem .55rem 1.6rem;
  border-radius: 999px;
  text-decoration: none;
  box-shadow: 0 8px 20px rgba(255, 45, 108, .35);
  transition: transform .25s ease, box-shadow .25s ease;
}
.cta-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 26px rgba(255, 45, 108, .45);
}
.cta-badge {
  display: grid;
  place-items: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 50%;
  background: #fff;
  color: var(--color-primary);
  flex: none;
}
.cta-badge svg { width: 1rem; height: 1rem; }
</style>
