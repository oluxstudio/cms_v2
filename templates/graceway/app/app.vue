<template>
  <!-- Teal page frame with the floating white canvas — the Graceway look. -->
  <div class="canvas">
    <Transition name="loader-fade">
      <AppLoader v-if="loading || booting" />
    </Transition>
    <!-- pages mount only once the CMS payload is in, so setup-time reads of
         useSiteContent()/useMembers() capture CMS values, not authored seeds -->
    <NuxtPage v-if="!booting" />
  </div>
</template>

<script setup lang="ts">
// Page-transition loader: shown while a route's page is loading, removed when ready.
const loading = ref(false)
const nuxtApp = useNuxtApp()
nuxtApp.hook('page:start', () => { loading.value = true })
nuxtApp.hook('page:finish', () => { loading.value = false })

// Cold-start gate: hold the loader until the CMS payload (block content +
// collections) is in, so the FIRST paint is CMS data — never the authored
// fallbacks flashing and then swapping. With a cached snapshot in
// localStorage both flags are true synchronously, so reloads skip this.
const booting = ref(true)
onMounted(() => {
  const { loaded } = ensureOluxContent()
  const { ready } = useCms()
  watchEffect(() => { if (loaded.value && ready.value) booting.value = false })
  setTimeout(() => { booting.value = false }, 8000) // CMS down — show the site anyway
})
</script>
