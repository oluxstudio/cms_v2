<script setup lang="ts">
import { reactive, ref } from 'vue'

const props = defineProps<{ siteName: string }>()
const form = reactive({ name: '', email: '', subject: '', message: '' })
const status = ref<'idle' | 'loading' | 'success' | 'error'>('idle')
const msg = ref('')

async function submit() {
  status.value = 'loading'
  try {
    const api = (useRuntimeConfig().public.apiBase || '/').replace(/\/$/, '')
    const data: any = await $fetch(`${api}/api/sites/${encodeURIComponent(props.siteName)}/contact`, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: { ...form },
    })
    status.value = 'success'
    msg.value = data?.message || 'Message sent!'
    Object.assign(form, { name: '', email: '', subject: '', message: '' })
  } catch (e: any) {
    status.value = 'error'
    msg.value = e?.data?.message || 'Please try again.'
  }
}
</script>

<template>
  <section class="section section-alt">
    <div class="container">
      <p class="eyebrow">Contact</p>
      <h2 class="t-head">Get in touch</h2>
      <div v-if="status === 'success'" class="alert ok">✓ {{ msg }}</div>
      <form v-else class="form" @submit.prevent="submit">
        <div class="form-row">
          <input v-model="form.name" type="text" placeholder="Name *" required>
          <input v-model="form.email" type="email" placeholder="Email *" required>
        </div>
        <input v-model="form.subject" type="text" placeholder="Subject">
        <textarea v-model="form.message" rows="4" placeholder="Message *" required></textarea>
        <p v-if="status === 'error'" class="alert err">{{ msg }}</p>
        <button class="btn" type="submit" :disabled="status === 'loading'">
          {{ status === 'loading' ? 'Sending…' : 'Send Message' }}
        </button>
      </form>
    </div>
  </section>
</template>
