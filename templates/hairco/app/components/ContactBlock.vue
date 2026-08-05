<script setup lang="ts">
const olux = useOluxContent('contact')
const oluxFb: Record<string, string> = {"Text":"Contact us","Headline":"We'd love to hear from you","Subheadline":"Send a message","Subheadline B":"Message sent","Subheadline C":"Your message","Caption":"Name","Caption B":"Email","Caption C":"Message","Text B":"We usually reply within one business day to the email above.\n            Need us sooner? Call <b>+1 589 625 3256</b> during opening hours.","Text C":"Send another message","Text D":"Send message"}
import { ref } from 'vue'

const cards = olux.items('Card', {"Title":"title","Text":"text"}, [
  { title: 'Visit the salon', text: '14 Rosewood Avenue, Suite 2, Portland OR' },
  { title: 'Call us', text: '+1 589 625 3256' },
  { title: 'Email', text: 'hello@hairco.salon' },
  { title: 'Open', text: 'Mon–Fri 9:00–20:00 · Sat 10:00–18:00' },
], {})

const name = ref('')
const email = ref('')
const msg = ref('')
const sent = ref(false)
const onSend = () => { sent.value = true }

function sendAnother() {
  sent.value = false
  name.value = ''; email.value = ''; msg.value = ''
}
</script>

<template>
  <section class="contact" v-if="!olux.hidden()" :style="olux.rootStyle.value" :class="olux.rootClass.value">
    <div class="container">
      <div>
        <p class="eyebrow">{{ olux.t('Text', oluxFb['Text']) }}</p>
        <h2>{{ olux.t('Headline', oluxFb['Headline']) }}</h2>
        <div class="contact-cards" style="margin-top:1.6rem">
          <div v-for="c in cards" :key="c.title" class="contact-card">
            <h3>{{ c.title }}</h3>
            <p>{{ c.text }}</p>
          </div>
        </div>
      </div>
      <form class="appt-form" @submit.prevent="onSend">
        <h3>{{ olux.t('Subheadline', oluxFb['Subheadline']) }}</h3>

        <div v-if="sent" class="confirm-panel">
          <div class="confirm-head">
            <span class="confirm-badge">✓</span>
            <div>
              <h4>{{ olux.t('Subheadline B', oluxFb['Subheadline B']) }}</h4>
              <p class="slot-note">Thanks{{ name ? `, ${name}` : '' }} — your message is on its way to us.</p>
            </div>
          </div>
          <div class="confirm-section">
            <h5>{{ olux.t('Subheadline C', oluxFb['Subheadline C']) }}</h5>
            <div class="confirm-grid">
              <div class="confirm-item"><span>{{ olux.t('Caption', oluxFb['Caption']) }}</span><b>{{ name }}</b></div>
              <div class="confirm-item"><span>{{ olux.t('Caption B', oluxFb['Caption B']) }}</span><b>{{ email }}</b></div>
              <div class="confirm-item full"><span>{{ olux.t('Caption C', oluxFb['Caption C']) }}</span><b>{{ msg }}</b></div>
            </div>
          </div>
          <p class="confirm-note" v-html="olux.t('Text B', oluxFb['Text B'])"></p>
          <div class="confirm-actions">
            <button class="btn ghost" type="button" @click="sendAnother">{{ olux.t('Text C', oluxFb['Text C']) }}</button>
          </div>
        </div>

        <div v-else class="grid">
          <div>
            <label>Your name</label>
            <input v-model="name" type="text" name="name" placeholder="Jane Doe" required>
          </div>
          <div>
            <label>Email</label>
            <input v-model="email" type="email" name="email" placeholder="you@example.com" required>
          </div>
          <div class="full">
            <label>Message</label>
            <textarea v-model="msg" name="message" rows="5" placeholder="How can we help?" required></textarea>
          </div>
        </div>
        <button v-if="!sent" class="btn" type="submit">{{ olux.t('Text D', oluxFb['Text D']) }}</button>
      </form>
    </div>
  </section>
</template>
