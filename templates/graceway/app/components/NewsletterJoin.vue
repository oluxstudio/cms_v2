<script setup lang="ts">
const submitted = ref(false)
const { submit: cmsSubmit, sending, error: sendError } = useCmsForm('newsletter-join')
const sendForm = async (e: Event) => {
  const data = Object.fromEntries(new FormData(e.target as HTMLFormElement).entries())
  if (await cmsSubmit(data)) submitted.value = true
}
</script>

<template>
  <section class="newsletter">
    <div class="container">
      <div class="newsletter-grid">
        <div>
          <h2 data-olx-field="Headline">What happens when you join?</h2>
          <ul class="perks">
            <li>A personal welcome from our pastoral team</li>
            <li>Weekly newsletter with sermons, events and prayer points</li>
            <li>An invitation to our next newcomers' lunch</li>
            <li>Help finding a small group or ministry that fits you</li>
          </ul>
        </div>

        <form v-if="!submitted" class="newsletter-form" @submit.prevent="sendForm">
          <label>Full name<input type="text" name="name" placeholder="Your full name" required></label>
          <label>Email address<input type="email" name="email" placeholder="you@example.com" required></label>
          <label>Phone (optional)<input type="tel" name="phone" placeholder="+1 ..."></label>
          <label class="check"><input type="checkbox" name="visit" value="yes"> I'd like someone to contact me about visiting</label>
          <p v-if="sendError" class="form-error">{{ sendError }}</p>
          <button class="btn" type="submit" :disabled="sending">{{ sending ? 'Sending…' : 'Join the Church' }}</button>
          <p class="fine">We send one email a week and never share your details. Unsubscribe anytime.</p>
        </form>

        <div v-else class="newsletter-thanks">
          <h2>Welcome to the family! 🎉</h2>
          <p>Thank you for joining. Look out for a welcome email from us this week — and we'd love to see you on Sunday.</p>
          <NuxtLink class="btn" to="/">Back to Home</NuxtLink>
        </div>
      </div>
    </div>
  </section>
</template>
