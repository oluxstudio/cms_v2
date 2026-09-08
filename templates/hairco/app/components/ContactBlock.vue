<script setup lang="ts">
const oluxCms = useOluxContent('contact')
const oluxFb: Record<string, string> = {"Subheadline":"Send a message","Text":"Loading the form\u2026","Text B":"The contact form is not available right now \u2014 please call or email us instead.","Subheadline B":"Message sent","Text C":"Send another message"}
const { field } = useCms()

import { ref, computed, onMounted } from 'vue'

// Phone/email come from the shared CMS "Global Contact" component (see
// GlobalPhone/GlobalEmail) — one edit in the CMS updates them site-wide.
const cards = computed(() => [
  { title: 'Visit the salon', text: '14 Rosewood Avenue, Suite 2, Portland OR' },
  { title: 'Call us', text: field('globalContact', 'phone', '+44 7527 685736') },
  { title: 'Email', text: field('globalContact', 'email', 'hello@hairco.salon') },
  { title: 'Open', text: 'Mon–Fri 9:00–20:00 · Sat 10:00–18:00' },
])

// ── Contact form is defined in and submitted to the CMS form API ──
// Same site resolution as the booking page (?site= / ?booking= override, cached).
const formSite = 'hairco'

function resolveSite(): string {
  if (typeof window === 'undefined') return formSite
  const q = new URLSearchParams(window.location.search)
  const fromUrl = q.get('booking') || q.get('site') || ''
  if (fromUrl) { try { sessionStorage.setItem('olux-booking-site', fromUrl) } catch (_) {} return fromUrl }
  try { const cached = sessionStorage.getItem('olux-booking-site'); if (cached) return cached } catch (_) {}
  return formSite
}

const siteName = resolveSite()
// API origin: a CMS-served preview (/nuxt-preview/…) is ALWAYS same-origin with
// the API; off-CMS (nuxt dev, static exports) use the configured bookingApiBase.
const origin = typeof window !== 'undefined' ? window.location.origin : ''
const cmsServed = typeof window !== 'undefined' && window.location.pathname.startsWith('/nuxt-preview/')
const apiBase = cmsServed ? origin : ((useRuntimeConfig().public.bookingApiBase || '').replace(/\/$/, '') || origin)
const api = `${apiBase}/api/sites/${encodeURIComponent(siteName)}/form/contact`

type Field = {
  key: string
  label: string
  type: string
  required: boolean
  placeholder?: string | null
  options?: string[]
}

const fields = ref<Field[]>([])
const values = ref<Record<string, string>>({})
const fieldErrors = ref<Record<string, string>>({})
const loadingForm = ref(true)
const formUnavailable = ref(false)
const sending = ref(false)
const sent = ref(false)
const doneMessage = ref('')
const errorMessage = ref('')

onMounted(async () => {
  try {
    const res: any = await $fetch(api)
    fields.value = (res.fields || []) as Field[]
    if (!fields.value.length) formUnavailable.value = true
    for (const f of fields.value) values.value[f.key] = ''
  } catch (_) {
    formUnavailable.value = true
  }
  loadingForm.value = false
})

async function onSend() {
  errorMessage.value = ''
  fieldErrors.value = {}
  sending.value = true
  try {
    const res: any = await $fetch(api, { method: 'POST', body: { ...values.value } })
    doneMessage.value = res?.message || 'Thanks — your message is on its way to us.'
    sent.value = true
  } catch (e: any) {
    const errs = e?.data?.errors || {}
    for (const key of Object.keys(errs)) fieldErrors.value[key] = Array.isArray(errs[key]) ? errs[key][0] : String(errs[key])
    errorMessage.value = e?.data?.message || 'Sorry, we could not send your message — please try again or call us.'
  }
  sending.value = false
}

const senderName = () => values.value.full_name || values.value.name || ''

function sendAnother() {
  sent.value = false
  doneMessage.value = ''
  errorMessage.value = ''
  for (const f of fields.value) values.value[f.key] = ''
}
</script>

<template>
  <section class="contact" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div data-olx-key="contactIntro" data-olx-kind="component">
        <p class="eyebrow" data-olx-field="eyebrow">{{ field('contactIntro', 'eyebrow', 'Contact us') }}</p>
        <h2 data-olx-field="heading">{{ field('contactIntro', 'heading', "We'd love to hear from you") }}</h2>
        <div class="contact-cards" style="margin-top:1.6rem">
          <div v-for="c in cards" :key="c.title" class="contact-card">
            <h3>{{ c.title }}</h3>
            <p>{{ c.text }}</p>
          </div>
        </div>
      </div>
      <form class="appt-form" @submit.prevent="onSend">
        <h3 data-olx-field="subheadline">{{ oluxCms.t('Subheadline', oluxFb['Subheadline']) }}</h3>

        <p data-olx-field="text" v-if="loadingForm" class="slot-note">{{ oluxCms.t('Text', oluxFb['Text']) }}</p>
        <p data-olx-field="textB" v-else-if="formUnavailable" class="err">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>

        <div v-else-if="sent" class="confirm-panel">
          <div class="confirm-head">
            <span class="confirm-badge">✓</span>
            <div>
              <h4 data-olx-field="subheadlineB">{{ oluxCms.t('Subheadline B', oluxFb['Subheadline B']) }}</h4>
              <p class="slot-note">{{ doneMessage }}</p>
            </div>
          </div>
          <p class="confirm-note">
            We usually reply within one business day{{ senderName() ? `, ${senderName()}` : '' }}.
            Need us sooner? Call <b><GlobalPhone link /></b> during opening hours.
          </p>
          <div class="confirm-actions">
            <button data-olx-field="textC" class="btn ghost" type="button" @click="sendAnother">{{ oluxCms.t('Text C', oluxFb['Text C']) }}</button>
          </div>
        </div>

        <div v-else class="grid">
          <div v-for="f in fields" :key="f.key" :class="{ full: f.type === 'textarea' }">
            <label>{{ f.label }}</label>
            <select v-if="f.type === 'select'" v-model="values[f.key]" :name="f.key" :required="f.required">
              <option value="" disabled>Choose…</option>
              <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
            </select>
            <textarea v-else-if="f.type === 'textarea'" v-model="values[f.key]" :name="f.key" rows="5"
                      :placeholder="f.placeholder || 'How can we help?'" :required="f.required"></textarea>
            <input v-else v-model="values[f.key]" :type="f.type === 'text' ? 'text' : f.type" :name="f.key"
                   :placeholder="f.placeholder || ''" :required="f.required">
            <p v-if="fieldErrors[f.key]" class="field-err">{{ fieldErrors[f.key] }}</p>
          </div>
        </div>

        <button v-if="!loadingForm && !formUnavailable && !sent" class="btn" type="submit" :disabled="sending">
          {{ sending ? 'Sending…' : 'Send message' }}
        </button>
        <p v-if="errorMessage && !sent" class="err">{{ errorMessage }}</p>
      </form>
    </div>
  </section>
</template>
