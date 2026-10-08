<script setup lang="ts">
const oluxCms = useOluxContent('contact')
const oluxFb: Record<string, string> = {}
const sent = ref(false)
// CMS-connected: entries land in the owner's Forms inbox
const { submit: cmsSubmit, sending, error: sendError } = useCmsForm('contact')
const sendForm = async (e: Event) => {
  const data = Object.fromEntries(new FormData(e.target as HTMLFormElement).entries())
  if (await cmsSubmit(data)) sent.value = true
}

// authored copy comes from the global data source
const { contact } = useSiteContent()

// info card grids are CMS collections; computed so connect-editor saves
// re-render in place (useCms data is reactive)
const info = computed(() => useContactInfo())
// social platforms whose `available` is true in the Socials collection
const socials = computed(() => useSiteContent().socials.filter(s => s.available === true))
console.log(socials);

</script>

<template>
  <section class="contact-sec" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="ct-grid">
        <!-- contact information card -->
        <div class="ct-info">
          <h3 data-olx-field="infoTitle">{{ oluxCms.t('Info Title', contact.infoTitle) }}</h3>
          <p class="ct-intro" data-olx-field="intro">{{ oluxCms.t('Intro', contact.intro) }}</p>
          <div data-olx-panel="contact-info">
            <div data-olx-item v-for="i in info" :key="i.label" class="ct-row">
              <span class="icon">{{ i.icon }}</span>
              <div>
                <b>{{ i.label }}</b>
                <a v-if="i.href" :href="i.href">{{ i.value }}</a>
                <template v-else>
                  <p v-for="line in i.value.split('\n')" :key="line">{{ line }}</p>
                </template>
              </div>
            </div>
          </div>

          <!-- social media -->
          <div v-if="socials.length" class="ct-social">
            <b data-olx-field="followLabel">{{ oluxCms.t('Follow Label', contact.followLabel) }}</b>
            <div class="ct-social-links" data-olx-panel="socials">
              <a
                data-olx-item
                v-for="s in socials" :key="s.name" :href="s.href"
                target="_blank" rel="noopener" :aria-label="s.name" :title="s.name"
                :style="{ '--sc': s.color }"
              >
                <svg viewBox="0 0 24 24" fill="currentColor"><path :d="s.icon"/></svg>
              </a>
            </div>
          </div>
        </div>

        <!-- get in touch form panel -->
        <div class="ct-form-panel">
          <span class="ct-chip" data-olx-field="formChip">{{ oluxCms.t('Form Chip', contact.formChip) }}</span>
          <h2 data-olx-field="formTitle">{{ oluxCms.t('Form Title', contact.formTitle) }}</h2>
          <p class="ct-sub" data-olx-field="formSub">{{ oluxCms.t('Form Sub', contact.formSub) }}</p>

          <form v-if="!sent" class="ct-form" @submit.prevent="sendForm">
            <div class="row">
              <input type="text" name="name" placeholder="Name" required>
              <input type="email" name="email" placeholder="Email Address" required>
            </div>
            <div class="row">
              <input type="tel" name="phone" placeholder="Phone Number">
              <select name="topic" required>
                <option value="" disabled selected data-olx-field="topicPlaceholder">{{ oluxCms.t('Topic Placeholder', contact.topicPlaceholder) }}</option>
                <option v-for="t in contact.topics" :key="t">{{ t }}</option>
              </select>
            </div>
            <textarea name="message" placeholder="Message" required></textarea>
            <p v-if="sendError" class="form-error">{{ sendError }}</p>
            <button class="btn ct-send" type="submit" :disabled="sending">{{ sending ? 'Sending…' : 'Send Message' }} <span class="arrow">↗</span></button>
          </form>
          <div v-else class="ct-thanks">
            <p data-olx-field="thanks">{{ oluxCms.t('Thanks', contact.thanks) }}</p>
            <button class="btn ghost" type="button" @click="sent = false" data-olx-field="sendAnother">{{ oluxCms.t('Send Another', contact.sendAnother) }}</button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.ct-social { margin-top: 1.4rem; padding-top: 1.2rem; border-top: 1px solid #f1ece3; }
.ct-social b { display: block; font-size: 1rem; color: var(--color-secondary); margin-bottom: .8rem; }
.ct-social-links { display: flex; flex-wrap: wrap; gap: .7rem; }
.ct-social-links a { width: 44px; height: 44px; border-radius: 12px; background: var(--primary-soft); color: var(--color-secondary);
  display: grid; place-items: center; transition: background .2s, color .2s, transform .2s; }
.ct-social-links a:hover { background: var(--sc); color: #fff; transform: translateY(-2px); }
.ct-social-links svg { width: 20px; height: 20px; }
</style>
