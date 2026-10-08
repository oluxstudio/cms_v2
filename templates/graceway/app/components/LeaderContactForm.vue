<script setup lang="ts">
// The "Get In Touch" form on a leader's profile page. Each message is sent
// as the "leader-contact" form with two hidden fields — which leader it is
// for (title + name) and a ready-made subject — so the owner sees in the
// CMS inbox who every message was meant for.
const props = defineProps<{ member: string, firstName: string }>()
const subject = computed(() => `Message for ${props.member}`)

const sent = ref(false)
const { submit, sending, error } = useCmsForm('leader-contact')
const send = async (e: Event) => {
  const form = e.target as HTMLFormElement
  const data = Object.fromEntries(new FormData(form).entries())
  if (await submit(data)) {
    sent.value = true
    form.reset()
  }
}
</script>

<template>
  <form v-if="!sent" class="profile-form" data-olx-form="leader-contact" @submit.prevent="send">
    <input type="hidden" name="member" :value="member">
    <input type="hidden" name="subject" :value="subject">
    <div class="row">
      <input type="text" name="first" placeholder="First Name" required>
      <input type="text" name="last" placeholder="Last Name">
    </div>
    <input type="email" name="email" placeholder="Email Address" required>
    <textarea name="message" placeholder="Your Message" required></textarea>
    <p v-if="error" class="form-error">{{ error }}</p>
    <button class="btn" type="submit" :disabled="sending">{{ sending ? 'Sending…' : 'Send Message' }}</button>
  </form>
  <div v-else class="profile-form lcf-sent" role="status">
    <p class="lcf-title">Thank you — your message is on its way.</p>
    <p>{{ firstName }} will get back to you soon.</p>
    <button class="btn" type="button" @click="sent = false">Send another message</button>
  </div>
</template>

<style scoped>
.lcf-sent { text-align: center; }
.lcf-title { font-family: var(--font-heading); font-size: 1.35rem; color: var(--color-secondary); }
.lcf-sent p:not(.lcf-title) { color: #55606b; }
</style>
