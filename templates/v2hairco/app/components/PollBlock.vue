<script setup lang="ts">
// ── Poll block ───────────────────────────────────────────────────────────
// Renders the site's OPEN polls: visitors vote once (per option on multi-
// choice polls) and see live results. Hidden when no poll is open.
const { field } = useCms()

import { ref, onMounted } from 'vue'

const formSite = (useRuntimeConfig().public as any).cmsSite || 'v2-hairco'

function resolveSite(): string {
  if (typeof window === 'undefined') return formSite
  const q = new URLSearchParams(window.location.search)
  const fromUrl = q.get('booking') || q.get('site') || ''
  if (fromUrl) { try { sessionStorage.setItem('olux-booking-site', fromUrl) } catch (_) {} return fromUrl }
  try { const cached = sessionStorage.getItem('olux-booking-site'); if (cached) return cached } catch (_) {}
  return formSite
}

const siteName = resolveSite()
const origin = typeof window !== 'undefined' ? window.location.origin : ''
const cmsServed = typeof window !== 'undefined' && window.location.pathname.startsWith('/nuxt-preview/')
const apiBase = cmsServed ? origin : ((useRuntimeConfig().public.bookingApiBase || '').replace(/\/$/, '') || origin)
const api = `${apiBase}/api/sites/${encodeURIComponent(siteName)}/polls`

type Opt = { id: string; label: string; votes: number; share: number }
type Poll = { id?: string; slug: string; question: string; multiple: boolean; total_votes: number; total_voters?: number; options: Opt[] }

import { computed } from 'vue'

const polls = ref<Poll[]>([])
const urlPinned = ref(false)   // a ?poll= deep-link overrides the CMS pin
const loading = ref(true)
const picked = ref<Record<string, string[]>>({})       // slug → option ids
const voted = ref<Record<string, boolean>>({})          // slug → done (results shown)
const busy = ref<Record<string, boolean>>({})
const errors = ref<Record<string, string>>({})

const VOTED_KEY = 'olux-poll-voted'
function rememberVote(slug: string) {
  voted.value[slug] = true
  try {
    const seen = JSON.parse(localStorage.getItem(VOTED_KEY) || '[]')
    if (!seen.includes(slug)) { seen.push(slug); localStorage.setItem(VOTED_KEY, JSON.stringify(seen)) }
  } catch (_) {}
}

onMounted(async () => {
  try {
    const res: any = await $fetch(api)
    polls.value = res.polls || []
    // ?poll={slug} narrows the page to ONE poll (admin "View on site" links).
    const only = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('poll') : null
    if (only) polls.value = polls.value.filter(p => p.slug === only || p.id === only)
    if (only) urlPinned.value = true
    for (const p of polls.value) picked.value[p.slug] = []
    try {
      const seen: string[] = JSON.parse(localStorage.getItem(VOTED_KEY) || '[]')
      for (const s of seen) voted.value[s] = true
    } catch (_) {}
  } catch (_) { polls.value = [] }
  loading.value = false
})

function toggle(p: Poll, id: string) {
  const cur = picked.value[p.slug] || []
  if (p.multiple) {
    picked.value[p.slug] = cur.includes(id) ? cur.filter(x => x !== id) : [...cur, id]
  } else {
    picked.value[p.slug] = [id]
  }
}

async function vote(p: Poll) {
  const ids = picked.value[p.slug] || []
  if (!ids.length) return
  busy.value[p.slug] = true
  errors.value[p.slug] = ''
  try {
    const res: any = await $fetch(`${api}/${encodeURIComponent(p.slug)}/vote`, {
      method: 'POST',
      body: p.multiple ? { options: ids } : { option: ids[0] },
    })
    p.options = res.options || p.options
    p.total_votes = res.total_votes ?? p.total_votes
    p.total_voters = res.total_voters ?? p.total_voters
    rememberVote(p.slug)
  } catch (e: any) {
    if (e?.status === 409 || e?.response?.status === 409) {
      // Already voted — show the live results instead of an error.
      if (e?.data?.options) p.options = e.data.options
      rememberVote(p.slug)
    } else {
      errors.value[p.slug] = e?.data?.message || 'Could not record your vote — please try again.'
    }
  }
  busy.value[p.slug] = false
}

// A "Poll" field on the CMS component pins ONE poll wherever this block is
// placed — owners set it in the Content editor; blank shows every open poll.
const visible = computed(() => {
  if (urlPinned.value) return polls.value   // deep link already narrowed the list
  const pin = String(field('poll', 'poll', '') || '').trim()
  return pin ? polls.value.filter(p => p.slug === pin || p.id === pin) : polls.value
})
</script>

<template>
  <section class="contact poll-blk">
    <div v-if="!loading && visible.length" class="container">
      <div data-olx-key="pollIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="eyebrow">{{ field('pollIntro', 'eyebrow', 'Have your say') }}</p>
        <h2 data-olx-field="heading">{{ field('pollIntro', 'heading', 'Help us decide') }}</h2>
        <p class="slot-note" data-olx-field="note">{{ field('pollIntro', 'note', 'Cast your vote — results update live.') }}</p>
      </div>

      <div class="appt-form poll-card" v-for="p in visible" :key="p.slug">
        <h3>{{ p.question }}</h3>
        <p class="slot-note">🗳 {{ p.total_voters ?? p.total_votes }} {{ (p.total_voters ?? p.total_votes) === 1 ? 'person has' : 'people have' }} voted so far</p>
        <p v-if="p.multiple && !voted[p.slug]" class="slot-note">Pick as many as you like.</p>

        <!-- voting -->
        <div v-if="!voted[p.slug]" class="poll-options">
          <button v-for="o in p.options" :key="o.id" type="button" class="poll-opt"
                  :class="{ on: (picked[p.slug] || []).includes(o.id) }" @click="toggle(p, o.id)">
            <span class="tick">{{ (picked[p.slug] || []).includes(o.id) ? '✓' : '' }}</span>
            {{ o.label }}
          </button>
          <button class="btn" type="button" :disabled="busy[p.slug] || !(picked[p.slug] || []).length" @click="vote(p)">
            {{ busy[p.slug] ? 'Casting your vote…'
               : !(picked[p.slug] || []).length ? (p.multiple ? 'Tick an option above to vote' : 'Pick an option above to vote')
               : (p.multiple && (picked[p.slug] || []).length > 1 ? `Cast my ${(picked[p.slug] || []).length} votes` : 'Cast my vote') }}
          </button>
          <p v-if="errors[p.slug]" class="err">{{ errors[p.slug] }}</p>
        </div>

        <!-- results -->
        <div v-else class="poll-results">
          <div v-for="o in p.options" :key="o.id" class="poll-line">
            <div class="poll-line-head"><span>{{ o.label }}</span><b>{{ o.share }}%</b></div>
            <div class="poll-bar"><div class="poll-fill" :style="{ width: Math.max(3, o.share) + '%' }"></div></div>
          </div>
          <p class="slot-note">{{ p.total_voters ?? p.total_votes }} {{ (p.total_voters ?? p.total_votes) === 1 ? 'person has' : 'people have' }} voted — thanks for taking part!</p>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.poll-card { margin-bottom: 1.4rem; }
.poll-options { display: grid; gap: .55rem; margin-top: 1rem; }
.poll-opt { display: flex; align-items: center; gap: .6rem; text-align: left; padding: .7rem .9rem;
  border: 1px solid rgba(0,0,0,.12); border-radius: .8rem; background: transparent; cursor: pointer; font: inherit; }
.poll-opt.on { border-color: var(--accent, #111); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent, #111) 25%, transparent); }
.poll-opt .tick { width: 1.15rem; height: 1.15rem; border-radius: .35rem; border: 1px solid rgba(0,0,0,.25);
  display: inline-grid; place-items: center; font-size: .7rem; flex: none; }
.poll-opt.on .tick { background: var(--accent, #111); color: #fff; border-color: var(--accent, #111); }
.poll-results { display: grid; gap: .8rem; margin-top: 1rem; }
.poll-line-head { display: flex; justify-content: space-between; font-size: .92rem; margin-bottom: .3rem; }
.poll-bar { height: .55rem; border-radius: 999px; background: rgba(0,0,0,.08); overflow: hidden; }
.poll-fill { height: 100%; border-radius: 999px; background: var(--accent, #111); transition: width .4s; }
</style>
