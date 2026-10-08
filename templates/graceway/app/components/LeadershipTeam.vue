<script setup lang="ts">
const oluxCms = useOluxContent('leadership-team')
const oluxFb: Record<string, string> = {"Text":"Everyone","Text B":"Our leadership team will be introduced here soon.","Text C":"Want to talk to someone?","Text D":"Our pastors and leaders are here for you \u2014 for prayer, a question or a coffee after the service."}
// @olux-source leadership
// The whole leadership team — each card opens the leader's profile page.
// Leaders who lead ministries can be filtered by them.
const { pastorsHead } = useSiteContent()
const members = computed(() => useMembers())

const listOf = (v: unknown) => (Array.isArray(v) ? v : String(v ?? '').split(',')).map(x => String(x).trim()).filter(Boolean)
const ministries = computed(() => [...new Set(members.value.flatMap(m => listOf(m.ministries)))])
const active = ref<string | null>(null)
const shown = computed(() => active.value ? members.value.filter(m => listOf(m.ministries).includes(active.value!)) : members.value)
const initials = (name: string) => name.replace(/^(Rev|Pastor|Bishop|Dr|Mr|Mrs|Ms|Miss)\.?\s+/i, '').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]?.toUpperCase()).join('')
</script>

<template>
  <section class="lt-sec" data-olx-panel="leadership" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <div class="section-head center">
        <p class="eyebrow" data-olx-field="eyebrow">{{ oluxCms.t('Eyebrow', pastorsHead.eyebrow) }}</p>
        <h2 data-olx-field="title">{{ oluxCms.t('Title', pastorsHead.title) }}</h2>
        <p data-olx-field="textE">{{ oluxCms.t('Text E', pastorsHead.text) }}</p>
      </div>

      <nav v-if="ministries.length" class="lt-filter" aria-label="Filter leaders by ministry">
        <button data-olx-field="text" type="button" :class="{ on: !active }" @click="active = null">{{ oluxCms.t('Text', oluxFb['Text']) }}</button>
        <button v-for="m in ministries" :key="m" type="button" :class="{ on: active === m }" @click="active = m">{{ m }}</button>
      </nav>

      <p data-olx-field="textB" v-if="!members.length" class="lt-empty">{{ oluxCms.t('Text B', oluxFb['Text B']) }}</p>
      <div v-else class="lt-grid">
        <article v-for="m in shown" :key="m.slug" data-olx-item class="lt-card">
          <NuxtLink :to="`/leadership/${m.slug}`" class="lt-photo" tabindex="-1" aria-hidden="true">
            <img v-if="m.img" :src="m.img" :alt="`Portrait of ${m.name}`" loading="lazy">
            <span v-else class="lt-initials">{{ initials(m.name) }}</span>
            <span class="lt-role-pill">{{ m.role }}</span>
          </NuxtLink>
          <div class="lt-body">
            <h3><NuxtLink :to="`/leadership/${m.slug}`">{{ m.name }}</NuxtLink></h3>
            <p v-if="m.bio" class="lt-bio">{{ m.bio }}</p>
            <ul v-if="listOf(m.ministries).length" class="lt-tags">
              <li v-for="t in listOf(m.ministries)" :key="t">{{ t }}</li>
            </ul>
            <div class="lt-actions">
              <NuxtLink class="lt-link" :to="`/leadership/${m.slug}`">View profile <EventIcon name="arrow" :size="15" /></NuxtLink>
              <a v-if="m.email" class="lt-mail" :href="`mailto:${m.email}`" :title="`Email ${m.name}`" :aria-label="`Email ${m.name}`"><EventIcon name="mail" :size="17" /></a>
            </div>
          </div>
        </article>
      </div>

      <div class="lt-cta">
        <div>
          <p data-olx-field="textC" class="lt-cta-title">{{ oluxCms.t('Text C', oluxFb['Text C']) }}</p>
          <p data-olx-field="textD">{{ oluxCms.t('Text D', oluxFb['Text D']) }}</p>
        </div>
        <div class="lt-cta-actions">
          <NuxtLink class="btn" to="/contact">Contact us</NuxtLink>
          <NuxtLink class="btn ghost" to="/prayer">Ask for prayer</NuxtLink>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.lt-sec { padding: 4rem 0 4.5rem; }
.lt-filter { display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem; margin-top: 1.6rem; }
.lt-filter button { padding: .5rem 1.1rem; border-radius: 999px; border: 1.5px solid #e2dbd0; background: #fff; font: inherit; font-size: .95rem;
  font-weight: 700; color: var(--color-secondary); cursor: pointer; transition: all .2s; }
.lt-filter button:hover { border-color: var(--color-primary); color: var(--color-primary); }
.lt-filter button.on { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }

.lt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(17rem, 22rem)); justify-content: center; gap: 1.6rem; margin-top: 2.2rem; }
@media (max-width: 640px) { .lt-grid { grid-template-columns: 1fr; } }
.lt-card { background: #fff; border-radius: 24px; overflow: hidden; box-shadow: 0 16px 38px rgba(20, 24, 29, .09); display: flex; flex-direction: column;
  transition: transform .25s ease, box-shadow .25s ease; }
.lt-card:hover { transform: translateY(-5px); box-shadow: 0 26px 52px rgba(20, 24, 29, .15); }
.lt-photo { position: relative; display: grid; place-items: center; aspect-ratio: 4 / 4.2; overflow: hidden;
  background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 35%, var(--color-secondary))); }
.lt-photo img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: top; transition: transform .45s ease; }
.lt-card:hover .lt-photo img { transform: scale(1.05); }
.lt-initials { font-family: var(--font-heading); font-size: 3.6rem; color: #fff; }
.lt-photo::after { content: ''; position: absolute; inset: auto 0 0; height: 45%; background: linear-gradient(transparent, rgba(10, 12, 16, .55)); }
.lt-role-pill { position: absolute; left: 1rem; bottom: 1rem; z-index: 1; max-width: calc(100% - 2rem); padding: .3rem .8rem; border-radius: 999px;
  background: #fff; color: var(--color-primary); font-size: .76rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lt-body { flex: 1; display: flex; flex-direction: column; gap: .55rem; padding: 1.3rem 1.5rem 1.4rem; }
.lt-body h3 { font-size: 1.5rem; }
.lt-body h3 a { color: inherit; }
.lt-body h3 a:hover { color: var(--color-primary); }
.lt-bio { font-size: .95rem; line-height: 1.6; color: #55606b; display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-line; }
.lt-tags { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; gap: .35rem; }
.lt-tags li { padding: .22rem .7rem; border-radius: 999px; background: var(--primary-soft); color: var(--color-primary); font-size: .78rem; font-weight: 700; }
.lt-actions { margin-top: auto; padding-top: .9rem; border-top: 1.5px dashed #ece5da; display: flex; align-items: center; justify-content: space-between; }
.lt-link { display: inline-flex; align-items: center; gap: .35rem; font-weight: 700; color: var(--color-primary); }
.lt-link:hover { gap: .55rem; }
.lt-mail { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 50%; background: var(--primary-soft); color: var(--color-primary); transition: all .2s; }
.lt-mail:hover { background: var(--color-primary); color: #fff; }
.lt-empty { margin-top: 2rem; text-align: center; font-size: 1.1rem; color: #55606b; }

.lt-cta { margin-top: 3.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem 2rem; flex-wrap: wrap; padding: 2rem 2.4rem;
  border-radius: 26px; color: #fff;
  background: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, .12) 1px, transparent 0) 0 0 / 20px 20px,
    linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 35%, var(--color-secondary)) 70%, var(--color-secondary)); }
.lt-cta-title { font-family: var(--font-heading); font-size: 1.7rem; margin-bottom: .3rem; }
.lt-cta p:not(.lt-cta-title) { opacity: .9; max-width: 34rem; }
.lt-cta-actions { display: flex; flex-wrap: wrap; gap: .7rem; }
.lt-cta .btn.ghost { color: #fff; border-color: rgba(255, 255, 255, .55); background: transparent; }
</style>
