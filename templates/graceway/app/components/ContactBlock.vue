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

// social platforms come from the global data source (RSS is broadcast-only)
const socials = useSiteContent().socials.filter(s => s.key !== 'rss')

// contact details & authored copy come from the global data source
const { profile, contact } = useSiteContent()
const info = oluxCms.items('Info', {"Icon":"icon","Label":"label"}, [
  { icon: '📱', label: 'Phone Number', value: profile.phone, href: profile.phoneHref },
  { icon: '✉️', label: 'Email Address', value: profile.email, href: `mailto:${profile.email}` },
  { icon: '🕐', label: 'Office Hours', lines: profile.officeHours.map(o => `${o.days} · ${o.hours}`) },
  { icon: '📍', label: 'Our Location', value: profile.address },
], {})
</script>

<template>
  <section class="contact-sec" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="ct-grid">
        <!-- contact information card -->
        <div class="ct-info">
          <h3>{{ contact.infoTitle }}</h3>
          <p class="ct-intro">{{ contact.intro }}</p>
          <div v-for="i in info" :key="i.label" class="ct-row">
            <span class="icon">{{ i.icon }}</span>
            <div>
              <b>{{ i.label }}</b>
              <a v-if="i.href" :href="i.href">{{ i.value }}</a>
              <template v-else-if="i.lines">
                <p v-for="line in i.lines" :key="line">{{ line }}</p>
              </template>
              <p v-else>{{ i.value }}</p>
            </div>
          </div>

          <!-- social media -->
          <div class="ct-social">
            <b>{{ contact.followLabel }}</b>
            <div class="ct-social-links">
              <a
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
          <span class="ct-chip">{{ contact.formChip }}</span>
          <h2>{{ contact.formTitle }}</h2>
          <p class="ct-sub">{{ contact.formSub }}</p>

          <form v-if="!sent" class="ct-form" @submit.prevent="sendForm">
            <div class="row">
              <input type="text" name="name" placeholder="Name" required>
              <input type="email" name="email" placeholder="Email Address" required>
            </div>
            <div class="row">
              <input type="tel" name="phone" placeholder="Phone Number">
              <select name="topic" required>
                <option value="" disabled selected>{{ contact.topicPlaceholder }}</option>
                <option v-for="t in contact.topics" :key="t">{{ t }}</option>
              </select>
            </div>
            <textarea name="message" placeholder="Message" required></textarea>
            <p v-if="sendError" class="form-error">{{ sendError }}</p>
            <button class="btn ct-send" type="submit" :disabled="sending">{{ sending ? 'Sending…' : 'Send Message' }} <span class="arrow">↗</span></button>
          </form>
          <div v-else class="ct-thanks">
            <p>{{ contact.thanks }}</p>
            <button class="btn ghost" type="button" @click="sent = false">{{ contact.sendAnother }}</button>
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
.ct-social-links a { width: 44px; height: 44px; border-radius: 12px; background: #fdeef3; color: var(--color-secondary);
  display: grid; place-items: center; transition: background .2s, color .2s, transform .2s; }
.ct-social-links a:hover { background: var(--sc); color: #fff; transform: translateY(-2px); }
.ct-social-links svg { width: 20px; height: 20px; }
</style>
