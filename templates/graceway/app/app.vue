<template>
  <div>
    <Transition name="loader-fade">
      <AppLoader v-if="loading || booting" />
    </Transition>
    <!-- pages mount only once the CMS payload is in, so setup-time reads of
         useSiteContent()/useMembers() capture CMS values, not authored seeds -->
    <NuxtLayout v-if="!booting">
      <NuxtPage :page-key="(r: any) => `${r.fullPath}#${rev}`" />
    </NuxtLayout>
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

  // Connect editor: collection edits re-render the page in place. Composables
  // read collection rows at component setup, so remount the current page once
  // fresh rows are in — keeping the scroll position — and tell the editor it
  // needn't reload the preview frame.
  window.addEventListener('olux:collections-updated', () => {
    const y = window.scrollY
    rev.value++
    nextTick(() => requestAnimationFrame(() => window.scrollTo({ top: y, behavior: 'instant' as ScrollBehavior })))
  })
  if (window.parent !== window) {
    window.parent.postMessage({ source: 'olx-connect', type: 'olx-live-collections' }, '*')
  }
})

// bumped to remount the page after the CMS collections change
const rev = ref(0)
</script>
