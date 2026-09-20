<script setup lang="ts">
const oluxCms = useOluxContent('prayer')
const oluxFb: Record<string, string> = {"Text":"Send Another"}
const { prayer } = useSiteContent()
const rhythms = prayer.rhythms

const sent = ref(false)
const { submit: cmsSubmit, sending, error: sendError } = useCmsForm('prayer')
const sendForm = async (e: Event) => {
  const data = Object.fromEntries(new FormData(e.target as HTMLFormElement).entries())
  if (await cmsSubmit(data)) sent.value = true
}
</script>

<template>
  <section class="prayer-sec" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <!-- about the prayer watch -->
      <div class="pr-intro">
        <div class="pr-intro-text">
          <span class="ct-chip">{{ prayer.intro.chip }}</span>
          <h2>{{ prayer.intro.title }}</h2>
          <p v-for="(para, pi) in prayer.intro.paragraphs" :key="pi">{{ para }}</p>
          <ul class="pr-facts">
            <li v-for="f in prayer.intro.facts" :key="f.label"><b>{{ f.label }}</b><span>{{ f.value }}</span></li>
          </ul>
        </div>
        <img class="pr-intro-img" :src="prayer.intro.img.src" :alt="prayer.intro.img.alt">
      </div>

      <!-- verse banner -->
      <div class="pr-verse">
        <p class="pr-verse-label">{{ prayer.verse.label }}</p>
        <blockquote>{{ prayer.verse.text }} <cite>{{ prayer.verse.cite }}</cite></blockquote>
      </div>

      <!-- prayer rhythms -->
      <div class="section-head center">
        <p class="eyebrow">{{ prayer.rhythmsEyebrow }}</p>
        <h2>{{ prayer.rhythmsTitle }}</h2>
      </div>
      <div class="pr-rhythms">
        <article v-for="r in rhythms" :key="r.title" class="pr-card">
          <span class="icon">{{ r.icon }}</span>
          <h3>{{ r.title }}</h3>
          <p class="pr-when">{{ r.when }} · {{ r.where }}</p>
          <p>{{ r.text }}</p>
        </article>
      </div>

      <!-- prayer request form -->
      <div class="ct-form-panel pr-form-panel">
        <span class="ct-chip">{{ prayer.form.chip }}</span>
        <h2>{{ prayer.form.title }}</h2>
        <p class="ct-sub">{{ prayer.form.sub }}</p>

        <form v-if="!sent" class="ct-form" @submit.prevent="sendForm">
          <div class="row">
            <input type="text" name="name" placeholder="Name (optional)">
            <input type="email" name="email" placeholder="Email (optional — for a reply)">
          </div>
          <textarea name="request" placeholder="Your prayer request" required></textarea>
          <label class="pr-check"><input type="checkbox" name="private"> Keep this between me and the prayer team only</label>
          <p v-if="sendError" class="form-error">{{ sendError }}</p>
          <button class="btn ct-send" type="submit" :disabled="sending">{{ sending ? 'Sending…' : 'Send Prayer Request' }} <span class="arrow">↗</span></button>
        </form>
        <div v-else class="ct-thanks">
          <p>{{ prayer.form.thanks }}</p>
          <button data-olx-field="text" class="btn ghost" type="button" @click="sent = false">{{ oluxCms.t('Text', oluxFb['Text']) }}</button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.prayer-sec { padding: 3rem 0 4rem; }

/* intro */
.pr-intro { display: flex; gap: 3rem; align-items: center; background: #fff; border-radius: 24px; padding: 2.4rem;
  box-shadow: 0 14px 32px rgba(20, 24, 29, .08); }
.pr-intro-text { flex: 1; }
.pr-intro-text .ct-chip { font-size: 1.15rem; }
.pr-intro-text h2 { margin: .8rem 0 .6rem; }
.pr-intro-text p { font-size: 17px; color: #55606b; line-height: 1.65; margin-top: .8rem; }
.pr-intro-img { flex: 0 0 40%; width: 40%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 18px; }

.pr-facts { list-style: none; padding: 0; margin: 1.4rem 0 0; display: grid; gap: .5rem; }
.pr-facts li { display: flex; gap: .8rem; font-size: 17px; color: #55606b; }
.pr-facts b { flex: 0 0 110px; color: var(--color-secondary); font-size: 1rem; }

/* verse banner — same treatment as the verse-of-the-week widget */
.pr-verse { margin: 2.4rem 0; background: var(--color-secondary); border-radius: 20px; padding: 2rem 2.4rem;
  box-shadow: 0 14px 32px rgba(20, 24, 29, .08); }
.pr-verse-label { font-weight: 800; font-size: 1rem; color: #f3e6d8; margin-bottom: .7rem; }
.pr-verse blockquote { font-family: var(--font-heading); font-size: 1.5rem; color: #fff; line-height: 1.4; }
.pr-verse cite { display: block; font-style: normal; font-size: 1rem; color: var(--color-primary); margin-top: .5rem; }

/* rhythms */
.pr-rhythms { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.8rem; margin-top: 2rem; }
.pr-card { background: #fff; border-radius: 20px; padding: 1.8rem; box-shadow: 0 14px 32px rgba(20, 24, 29, .08); }
.pr-card .icon { display: grid; place-items: center; width: 48px; height: 48px; border-radius: 12px; background: #fdeef3;
  font-size: 1.3rem; margin-bottom: 1rem; }
.pr-card h3 { font-size: 1.15rem; color: var(--color-secondary); margin-bottom: .3rem; }
.pr-when { font-weight: 700; color: var(--color-primary); font-size: 1rem; margin-bottom: .5rem; }
.pr-card p { font-size: 17px; color: #55606b; line-height: 1.6; }

/* request form */
.pr-form-panel { margin-top: 2.4rem; }
.pr-form-panel .ct-chip { font-size: 1.15rem; }
.pr-form-panel .ct-sub { font-size: 17px; }
.pr-check { display: flex; align-items: center; gap: .6rem; font-size: 17px; color: #55606b; margin: .4rem 0 1rem; }
.pr-check input { width: 18px; height: 18px; accent-color: var(--color-primary); }

@media (max-width: 1024px) { .pr-rhythms { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 900px) {
  .pr-intro { flex-direction: column; padding: 1.4rem; }
  .pr-intro-img { flex: none; width: 100%; }
}
@media (max-width: 600px) { .pr-rhythms { grid-template-columns: 1fr; } }
</style>
