<script setup lang="ts">
// Top bar above the header: contact details from the site's properties,
// social links from the Socials collection (switched-on platforms only, as in
// the footer; Zoom is a broadcast channel, not a profile to follow).
// @olux-source socials
// computed so connect-editor saves re-render in place (useCms data is reactive)
// The CMS Properties page (Contact: main phone · Business: street, town)
// wins; the Site Profile collection is the template's fallback.
const { data } = ensureOluxContent()
const contact = computed(() => {
  const { profile } = useSiteContent()
  const props = data.value?.site?.properties ?? {}
  const phone = props.phones?.[0]?.value || profile.phone
  const addr = props.business?.address ?? {}
  const address = [addr.street || profile.address, addr.town || profile.city].filter(Boolean).join(', ')
  return { phone, phoneHref: phone === profile.phone ? profile.phoneHref : '', address }
})
const followable = computed(() => useSiteContent().socials.filter(s => s.available && s.key !== 'zoom'))
</script>

<template>
  <!-- scrolls away; the main header below stays sticky -->
  <div class="topbar">
    <div class="container">
      <ul class="topbar-info" data-olx-panel="site-properties" data-olx-fields="phones,address_street,address_town">
        <li v-if="contact.phone">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.6.1.3 0 .7-.2 1l-2.3 2.2z"/></svg>
          <a :href="contact.phoneHref || `tel:${contact.phone.replace(/\s+/g, '')}`">{{ contact.phone }}</a>
        </li>
        <li v-if="contact.address">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
          <span>{{ contact.address }}</span>
        </li>
      </ul>
      <div class="topbar-social" data-olx-panel="socials">
        <a
          data-olx-item
          v-for="s in followable" :key="s.key" :href="s.href"
          target="_blank" rel="noopener" :aria-label="s.name" :title="s.name"
        ><svg viewBox="0 0 24 24" fill="currentColor"><path :d="s.icon"/></svg></a>
      </div>
    </div>
  </div>
</template>
