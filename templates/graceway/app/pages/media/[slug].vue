<script setup lang="ts">
const route = useRoute()
// CMS-backed media arrives async — resolve reactively so late rows still land.
const all = computed(() => useAllMedia())
const item = computed(() => all.value.find(m => m.slug === route.params.slug))

useHead({ title: computed(() => item.value ? `${item.value.title} — CAC Blackburn` : 'Media — CAC Blackburn') })

// Engagement beacons — the owner's per-item analytics (views + plays).
const { send } = useMediaBeacon()
watch(item, v => { if (v) send(v, 'view') }, { immediate: true })

// related: same category first, then other playable media — never the current item
const related = computed(() => {
  if (!item.value) return []
  const playable = all.value.filter(m => m.slug !== item.value!.slug && (m.type === 'video' || m.type === 'audio'))
  const same = playable.filter(m => m.cat === item.value!.cat)
  const rest = playable.filter(m => m.cat !== item.value!.cat)
  return [...same, ...rest].slice(0, 6)
})

// comments — per-media, persisted locally (prototype; CMS-backed later)
interface MComment { name: string, text: string, when: string }
const seed: MComment[] = [
  { name: 'Amara', text: 'This blessed me so much — watched it twice! 🙌', when: '2 days ago' },
  { name: 'Joseph', text: 'The choir was on fire this week. Glory!', when: '1 day ago' },
]
const comments = ref<MComment[]>(seed)
const storageKey = `media-comments-${route.params.slug}`
onMounted(() => {
  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '[]')
    comments.value = [...seed, ...saved]
  } catch {}
})
const cName = ref('')
const cText = ref('')
const addComment = () => {
  const c: MComment = { name: cName.value || 'Anonymous', text: cText.value, when: 'Just now' }
  comments.value.push(c)
  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '[]')
    saved.push(c)
    localStorage.setItem(storageKey, JSON.stringify(saved))
  } catch {}
  cText.value = ''
}

const badge = { video: '▶ Video', audio: '🎧 Audio', image: '📷 Photo' } as const
</script>

<template>
  <div v-if="item">
    <SiteHeader />
    <div v-if="!item" class="container" style="padding:6rem 24px; text-align:center">
      <h1>Media not found</h1>
      <p><NuxtLink to="/worship">← Back to Worship</NuxtLink></p>
    </div>
    <template v-else>
    <section class="page-hero media-hero">
      <div class="container">
        <BreadCrumbs :items="[{ label: 'Ministries', to: '/ministries' }, { label: 'Media' }, { label: item.title }]" />
        <!-- <PageHeroContent> -->
          <p class="eyebrow"> {{ item.cat }}</p>
          <h1>{{ item.title }}</h1>
          <p>{{ item.date }}</p>
        <!-- </PageHeroContent> -->
      </div>
    </section>

    <div class="container youth-layout">
      <div class="youth-main media-main">
        <!-- player -->
        <section class="media-player-sec">
          <div class="container">
            <div v-if="item.type === 'video'" class="sermon-media video">
              <video :src="item.src" :poster="item.img" controls autoplay playsinline @play="send(item, 'play')"></video>
            </div>
            <div v-else class="sermon-media audio">
              <img :src="item.img || '/assets/images/gallery-9.jpg'" :alt="item.title">
              <audio :src="item.src" controls @play="send(item, 'play')"></audio>
            </div>
          </div>
        </section>

        <!-- comments -->
        <section class="media-comments">
          <div class="container">
            <h2>{{ comments.length }} Comments</h2>
            <form class="mc-form" @submit.prevent="addComment">
              <input v-model="cName" type="text" placeholder="Your name (optional)">
              <div class="row">
                <textarea v-model="cText" required placeholder="Add a comment…"></textarea>
                <button class="btn" type="submit">Post</button>
              </div>
            </form>
            <div v-for="c in comments" :key="c.name + c.text" class="mc-item">
              <span class="avatar">{{ c.name.charAt(0).toUpperCase() }}</span>
              <div>
                <b>{{ c.name }} <em>· {{ c.when }}</em></b>
                <p>{{ c.text }}</p>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- related media sidebar -->
      <aside class="youth-sidebar">
        <div class="ys-widget">
          <p class="ys-label">📺 Related</p>
          <NuxtLink v-for="r in related" :key="r.slug" class="mr-item" :to="`/media/${r.slug}`">
            <span class="thumb">
              <img :src="r.img || '/assets/images/gallery-9.jpg'" :alt="r.title">
              <i>{{ r.type === 'video' ? '▶' : '🎧' }}</i>
            </span>
            <span class="info">
              <b>{{ r.title }}</b>
              <small>{{ r.cat }} · {{ r.date }}</small>
            </span>
          </NuxtLink>
        </div>
        <div class="ys-widget ys-newhere">
          <p class="ys-label">📡 Never miss a Sunday</p>
          <NuxtLink class="btn dark" to="/broadcast">Watch Live</NuxtLink>
          <NuxtLink class="btn ghost" to="/newsletter">Get Weekly Updates</NuxtLink>
        </div>
      </aside>
    </div>
    </template>

    <SiteFooter />
  </div>
</template>
