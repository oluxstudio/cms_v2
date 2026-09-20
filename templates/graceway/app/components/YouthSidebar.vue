<script setup lang="ts">
// meet the leaders — from the leadership data source; each row opens the profile page
const members = useMembers()
const youthLeaderSlugs = ['samuel-reyes', 'grace-lindqvist', 'ruth-alonso']
const leaders = youthLeaderSlugs
  .map(s => members.find(m => m.slug === s)!)
  .filter(Boolean)
const firstName = (name: string) => name.replace(/^Rev\. /, '').split(' ')[0]

// "this or that" poll — one vote per visitor, persisted locally
const options = [
  { id: 'movie', label: '🎬 Movie Night', votes: 23 },
  { id: 'bowling', label: '🎳 Bowling Trip', votes: 31 },
  { id: 'games', label: '🎮 Games Tournament', votes: 17 },
]
const voted = ref<string | null>(null)
onMounted(() => { try { voted.value = localStorage.getItem('youth-poll') } catch {} })
const vote = (id: string) => {
  if (voted.value) return
  voted.value = id
  try { localStorage.setItem('youth-poll', id) } catch {}
}
const total = computed(() => options.reduce((s, o) => s + o.votes + (voted.value === o.id ? 1 : 0), 0))
const pct = (o: typeof options[0]) => Math.round(((o.votes + (voted.value === o.id ? 1 : 0)) / total.value) * 100)
</script>

<template>
  <aside class="youth-sidebar">
    <!-- verse of the week -->
    <div class="ys-widget ys-verse">
      <p class="ys-label">📖 Verse of the week</p>
      <blockquote>"Don't let anyone look down on you because you are young." <cite>— 1 Tim 4:12</cite></blockquote>
    </div>

    <!-- meet the leaders -->
    <div class="ys-widget">
      <p class="ys-label">👋 Meet the leaders</p>
      <NuxtLink v-for="l in leaders" :key="l.slug" class="ys-leader" :to="`/leadership/${l.slug}`">
        <img :src="l.img" :alt="`Photo of ${firstName(l.name)}`">
        <div>
          <b>{{ firstName(l.name) }}</b>
          <p> <small>{{ l.role }}</small> </p>
          <p>{{ l.fact }}</p>
        </div>
        <span class="ys-leader-go" aria-hidden="true">→</span>
      </NuxtLink>
    </div>

    <!-- this-or-that poll -->
    <div class="ys-widget">
      <p class="ys-label">🗳 You decide the next social</p>
      <div class="yv-poll">
        <button
          v-for="o in options" :key="o.id" type="button"
          class="yv-poll-opt" :class="{ picked: voted === o.id, done: voted }"
          @click="vote(o.id)"
        >
          <span class="bar" :style="voted ? { width: pct(o) + '%' } : {}"></span>
          <span class="label">{{ o.label }}</span>
          <span v-if="voted" class="pct">{{ pct(o) }}%</span>
        </button>
      </div>
      <p class="ys-fine">{{ voted ? `${total} votes so far` : 'Tap to vote · one each' }}</p>
    </div>

    <!-- new here? -->
    <div class="ys-widget ys-newhere">
      <p class="ys-label">✨ New here?</p>
      <ul>
        <li><b>When:</b> Fridays 6:30 PM — just walk in</li>
        <li><b>Where:</b> Youth Hall, behind the main building</li>
        <li><b>Wear:</b> whatever. Hoodies win.</li>
      </ul>
      <a class="btn dark" href="#">📱 Join the WhatsApp</a>
      <a class="btn ghost" href="#">📸 @cacblackburn.youth</a>
    </div>
  </aside>
</template>
