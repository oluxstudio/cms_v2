<script setup>
const submitted = ref(false)
</script>
<template>
  <section>
    <form data-olx-form="Welcome Signup" @submit.prevent="submitted = true">
      <label>Full name<input type="text" name="name" placeholder="Your full name" required></label>
      <label>Email<input type="email" name="email" required></label>
      <label>Message<textarea name="message" placeholder="How can we help?"></textarea></label>
      <button type="submit">Send</button>
    </form>
  </section>
</template>
