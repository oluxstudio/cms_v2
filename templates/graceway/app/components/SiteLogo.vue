<script setup lang="ts">
// Header logo: the round badge, a vertical golden divider and the Mount Zion
// logo — both images are fixed in the template (not editable in the CMS).
const base = ((useRuntimeConfig() as any).app?.baseURL || '/').replace(/\/?$/, '/')
const badgeSrc = `${base}assets/images/logo.png`
const mainSrc = `${base}assets/images/cac-mount-zion-logo.png`
// unique gradient ids (the header can render more than once)
const gid = useId()
</script>

<template>
  <div class="logo-group paired">
    
    <NuxtLink to="/" class="logo">
      <img :src="badgeSrc" alt="Church badge" class="logo-img">
    </NuxtLink>
    <!-- golden divider between the two logos: tapered rule, a four-point star, diamond tips -->
    <svg class="logo-divider" viewBox="0 0 32 120" aria-hidden="true" focusable="false">
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
	<NuxtLink to="/" class="logo-second" aria-label="Home">
      <img :src="mainSrc" alt="Church logo">
    </NuxtLink>
  </div>
</template>

<style scoped>
/* the badge is a --logo-h square; the main logo is 80% of it at every width */
.logo-group { --logo-h: 100px; display: flex; align-items: center; gap: .8rem; flex: none; }
/* the main logo, on a white badge so its dark lettering reads over the hero's dark header too —
   the white badge sits INSIDE its box */
.logo-second { display: grid; place-items: center; flex: none; box-sizing: border-box; width: calc(var(--logo-h) * .8); height: calc(var(--logo-h) * .8);
  padding: calc(var(--logo-h) * .056); border-radius: calc(var(--logo-h) * .16); background: #fff; line-height: 0;
  box-shadow: 0 6px 18px rgba(20, 24, 29, .14); transition: transform .2s ease; }
.logo-second:hover { transform: translateY(-2px); }
.logo-second img { display: block; width: 100%; height: 100%; object-fit: contain; }
.logo-divider { flex: none; height: calc(var(--logo-h) + .4rem); width: auto; filter: drop-shadow(0 1px 1.5px rgba(80, 52, 6, .35)); }
.logo-group.paired .logo .logo-img { width: var(--logo-h) !important; height: var(--logo-h) !important; }
@media (max-width: 1500px) { .logo-group { --logo-h: 85px; gap: .7rem; } }
@media (max-width: 1230px) { .logo-group { --logo-h: 75px; } }
@media (max-width: 640px) { .logo-group { --logo-h: 60px; gap: .45rem; } }
</style>
