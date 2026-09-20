<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()
const is404 = computed(() => props.error?.statusCode === 404)

useHead({ title: `${props.error?.statusCode || 'Error'} — CAC Blackburn` })

const goHome = () => clearError({ redirect: '/' })
</script>

<template>
  <div class="err-page">
    <!-- dot pattern — top-left only, fading into the gradient -->
    <span class="err-dots" aria-hidden="true"></span>

    <!-- floating translucent circles + bottom waves -->
    <span class="err-circle c1" aria-hidden="true"></span>
    <span class="err-circle c2" aria-hidden="true"></span>
    <span class="err-circle c3" aria-hidden="true"></span>
    <svg class="err-wave" viewBox="0 0 1440 320" preserveAspectRatio="none" aria-hidden="true">
      <path fill="rgba(255,255,255,.08)" d="M0,224 C240,300 480,160 720,200 C960,240 1200,120 1440,190 L1440,320 L0,320 Z"/>
      <path fill="rgba(255,255,255,.06)" d="M0,270 C280,200 560,300 840,250 C1080,210 1280,280 1440,240 L1440,320 L0,320 Z"/>
    </svg>

    <!-- church logo -->
    <button class="err-logo" type="button" aria-label="Back to home" @click="goHome">
      <img src="/assets/images/logo.png" alt="Church logo" onerror="this.remove()">
      <span>CAC <small>Blackburn.</small></span>
    </button>

    <div class="err-layout">
      <div class="err-copy">
        <div class="err-code" aria-hidden="true">
          <span class="err-num">4</span>
          <span class="pill tint-red err-zero">
            <img src="/assets/images/event-3.jpg" alt="">
            <span class="pill-label">Lost?</span>
          </span>
          <span class="err-num">4</span>
        </div>

        <h1>{{ is404 ? 'Page Not Found' : 'Something Went Wrong' }}</h1>
        <p class="err-sub">
          {{ is404
            ? 'Sorry, we can\'t find the page you\'re looking for — even our search party came back empty-handed.'
            : (error?.statusMessage || 'An unexpected error occurred — try again, or head back home.') }}
        </p>

        <button class="err-btn" type="button" @click="goHome">BACK TO HOME</button>

      <!-- dev-only: show the real error so it can be fixed -->
      <pre v-if="$config.public && error && !is404" class="err-debug">{{ error.message }}
{{ error.stack }}</pre>
      </div>
    </div>
  </div>
</template>

<style scoped>
.err-page { position: relative; min-height: 100vh; overflow: hidden; color: #fff;
  display: grid; align-items: center; padding: 5.5rem clamp(1.5rem, 5vw, 5rem) 3rem;
  background: linear-gradient(135deg,
    color-mix(in srgb, var(--color-primary) 80%, #7b2ff7) 0%,
    color-mix(in srgb, var(--color-primary) 45%, #4b2fb8) 55%,
    #3b2a8f 100%); }

/* dot pattern pinned to the top-left corner, dissolving into the gradient */
.err-dots { position: absolute; left: 0; top: 0; width: min(72vw, 950px); height: min(78vh, 760px); pointer-events: none;
  background-image: radial-gradient(circle at 2px 2px, rgba(255, 255, 255, .75) 1.6px, transparent 2px);
  background-size: 20px 20px;
  -webkit-mask-image: radial-gradient(120% 120% at 0% 0%, #000 0%, rgba(0, 0, 0, .5) 45%, transparent 72%);
          mask-image: radial-gradient(120% 120% at 0% 0%, #000 0%, rgba(0, 0, 0, .5) 45%, transparent 72%); }

/* logo, top-left like the site header */
.err-logo { position: absolute; left: clamp(1rem, 4vw, 3rem); top: 1.4rem; z-index: 2;
  display: flex; align-items: center; gap: .7rem; background: none; border: 0; cursor: pointer; color: #fff; font: inherit; }
.err-logo img { width: 56px; height: 56px; object-fit: contain; border-radius: 50%; background: #fff;
  box-shadow: 0 8px 20px rgba(0, 0, 0, .25); }
.err-logo span { font-family: var(--font-heading); font-size: 1.2rem; letter-spacing: .02em; }
.err-logo small { display: block; font-size: .7rem; opacity: .85; text-align: left; }

/* floating translucent circles */
.err-circle { position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .1); }
.c1 { width: 90px; height: 90px; right: 24%; top: 10%; }
.c2 { width: 44px; height: 44px; left: 40%; bottom: 22%; background: rgba(255, 255, 255, .16); }
.c3 { width: 140px; height: 140px; right: 6%; bottom: 14%; background: rgba(255, 255, 255, .07); }

/* bottom waves */
.err-wave { position: absolute; left: 0; right: 0; bottom: 0; width: 100%; height: 30vh; pointer-events: none; }

.err-layout { position: relative; z-index: 1; display: grid; place-items: center; width: 100%; }

.err-copy { text-align: center; }
.err-code { display: flex; align-items: center; justify-content: center; gap: clamp(.7rem, 1.6vw, 1.3rem); margin-bottom: 1.4rem; }
.err-num { font-family: var(--font-heading); font-size: clamp(6rem, 13vw, 10.5rem); line-height: 1; color: #fff;
  text-shadow: 0 14px 34px rgba(0, 0, 0, .18); }
.err-zero { width: clamp(88px, 10vw, 130px); height: clamp(124px, 15vw, 190px); flex: none;
  box-shadow: 0 14px 34px rgba(0, 0, 0, .25); }

.err-page h1 { font-size: clamp(1.8rem, 3.6vw, 2.6rem); color: #fff; }
.err-sub { font-size: 17px; color: rgba(255, 255, 255, .85); line-height: 1.65; max-width: 460px; margin: .8rem auto 1.8rem; }

.err-btn { font: inherit; font-weight: 800; font-size: .85rem; letter-spacing: .14em; color: #fff; cursor: pointer;
  background: var(--color-primary); border: 0; border-radius: 999px; padding: .95rem 2.4rem;
  box-shadow: 0 12px 28px rgba(0, 0, 0, .25); transition: transform .2s ease, box-shadow .2s ease; }
.err-btn:hover { transform: translateY(-2px); box-shadow: 0 16px 34px rgba(0, 0, 0, .3); }

.err-debug { margin: 2rem auto 0; max-width: 720px; max-height: 40vh; overflow: auto; text-align: left;
  background: rgba(0, 0, 0, .35); border-radius: 12px; padding: 1rem 1.2rem; font-size: .78rem; line-height: 1.5;
  color: #ffd7de; white-space: pre-wrap; word-break: break-word; }

</style>
