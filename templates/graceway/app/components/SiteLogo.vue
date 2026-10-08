<script setup lang="ts">
// Header logo. With a Site Profile › Second logo, that logo leads as the main
// mark, then a vertical golden divider, then the badge logo; without one, the
// badge logo stands alone. Badge image + wordmark: The CMS Properties page (Brand →
// Logo / Logo Text / Logo Subtext) wins; the Site Profile collection's
// Logo image / Logo text / Logo small line are the template's own values
// (the CMS keeps the two in step).
const { data } = ensureOluxContent()
// computed so connect-editor saves re-render in place (useCms data is reactive)
const logo = computed(() => {
  const { profile } = useSiteContent()
  const brand = data.value?.site?.properties ?? {}
  const text = (v: unknown) => (typeof v === 'string' && v.trim() ? v : '')
  return {
    img: text(brand.logo) || profile.logoImg,
    lines: contentLines(text(brand.logo_text) || profile.logoText || ''),
    sub: text(brand.logo_subtext) || profile.logoSub,
    // the main mark, first in the header (Site Profile › Second logo)
    second: text(profile.logoImg2),
  }
})
// unique gradient ids (the header can render more than once)
const gid = useId()
</script>

<template>
  <div class="logo-group" :class="{ paired: logo.second }">
    <NuxtLink v-if="logo.second" to="/" class="logo-second" data-olx-panel="site-profile" data-olx-fields="logoImg2" aria-label="Home">
      <img :src="logo.second" alt="Church logo" onerror="this.parentElement.remove()">
    </NuxtLink>
    <!-- golden divider between the two logos: tapered rule, a four-point star, diamond tips -->
    <svg v-if="logo.second" class="logo-divider" viewBox="0 0 32 120" aria-hidden="true" focusable="false">
      <defs>
        <linearGradient :id="`${gid}-gold`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#f7e7a1" />
          <stop offset=".3" stop-color="#d9a93b" />
          <stop offset=".5" stop-color="#fbe9a6" />
          <stop offset=".7" stop-color="#c8922a" />
          <stop offset="1" stop-color="#f2d47c" />
        </linearGradient>
        <linearGradient :id="`${gid}-shine`" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#fff6d6" />
          <stop offset="1" stop-color="#e2b04a" />
        </linearGradient>
      </defs>
      <g :fill="`url(#${gid}-gold)`">
        <!-- diamond tips -->
        <path d="M16 0 L19.5 5 L16 10 L12.5 5 Z" />
        <path d="M16 110 L19.5 115 L16 120 L12.5 115 Z" />
        <!-- tapered rules -->
        <path d="M16 12 L18.1 32 L16 46 L13.9 32 Z" />
        <path d="M16 74 L18.1 88 L16 108 L13.9 88 Z" />
        <!-- four-point star -->
        <path d="M16 44 L19.3 56.7 L32 60 L19.3 63.3 L16 76 L12.7 63.3 L0 60 L12.7 56.7 Z" />
      </g>
      <path d="M16 52.5 L19.6 60 L16 67.5 L12.4 60 Z" :fill="`url(#${gid}-shine)`" />
      <circle cx="16" cy="60" r="1.7" fill="#a8741a" />
    </svg>
    <NuxtLink to="/" class="logo" data-olx-panel="site-properties" data-olx-fields="logo,logo_text,logo_subtext">
      <img v-if="logo.img" :src="logo.img" alt="Church badge" class="logo-img" onerror="this.remove()">
      <!-- <span class="logo-text">
        <template v-for="(line, i) in logo.lines" :key="i">{{ line }}<br v-if="i < logo.lines.length - 1"></template>
        <small v-if="logo.sub">{{ logo.sub }}</small>
      </span> -->
    </NuxtLink>
  </div>
</template>

<style scoped>
.logo-group { --logo-h: 84px; display: flex; align-items: center; gap: .9rem; flex: none; }
/* the main logo, on a white badge so its dark lettering reads over the hero's dark header too */
.logo-second { display: block; flex: none; padding: .3rem .45rem; border-radius: 16px; background: #fff; line-height: 0;
  box-shadow: 0 6px 18px rgba(20, 24, 29, .14); transition: transform .2s ease; }
.logo-second:hover { transform: translateY(-2px); }
.logo-second img { display: block; height: var(--logo-h); width: auto; max-width: 170px; object-fit: contain; }
.logo-divider { flex: none; height: calc(var(--logo-h) + .6rem); width: auto; filter: drop-shadow(0 1px 1.5px rgba(80, 52, 6, .35)); }
/* beside the main logo the badge steps down to the same height */
.logo-group.paired .logo .logo-img { width: var(--logo-h) !important; height: var(--logo-h) !important; }
@media (max-width: 1500px) { .logo-group { --logo-h: 76px; gap: .7rem; } }
@media (max-width: 1230px) { .logo-group { --logo-h: 68px; } }
@media (max-width: 640px) { .logo-group { --logo-h: 48px; gap: .45rem; } .logo-second { padding: .2rem .3rem; border-radius: 12px; } }
</style>
