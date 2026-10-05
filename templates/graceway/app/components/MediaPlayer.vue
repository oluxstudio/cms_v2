<script setup lang="ts">
// Reusable inline media player. Initial state is a facade: poster, gradient
// play button, title + tags. Clicking removes every overlay and plays the
// media in the same frame — local files or YouTube / Facebook / TikTok links
// (resolved by useMediaSource). Pass the CMS row as `item` for beacons.
const props = withDefaults(defineProps<{
  src: string
  /** force 'audio' for extension-less audio URLs */
  type?: 'video' | 'audio'
  poster?: string
  title?: string
  tags?: string[]
  /** override the action label ("▶ Watch" / "🎧 Listen") */
  label?: string
  /** CMS row (id + _cid) — enables view/play engagement beacons */
  item?: any
}>(), { tags: () => [] })

const resolved = computed(() => resolveMediaSource(props.src, props.type))
const isAudio = computed(() => resolved.value.kind === 'audio')
const isEmbed = computed(() => !!resolved.value.embed)
const actionLabel = computed(() => props.label ?? (isAudio.value ? '🎧 Listen' : '▶ Watch'))

const playing = ref(false)
const { send } = useMediaBeacon()
onMounted(() => { if (props.item) send(props.item, 'view') })
const play = () => {
  playing.value = true
  if (props.item) send(props.item, 'play')
}
</script>

<template>
  <div class="mp" :class="{ 'is-audio': isAudio, 'is-playing': playing }">
    <!-- ── facade: poster + gradient play + label/tags — all gone once playing ── -->
    <button v-if="!playing" type="button" class="mp-facade" :aria-label="`Play ${title || 'media'}`" @click="play">
      <img v-if="poster" class="mp-poster" :src="poster" :alt="title || 'Media poster'" loading="lazy">
      <span v-else class="mp-poster mp-poster-audio" aria-hidden="true"></span>

      <span class="mp-shade" aria-hidden="true"></span>

      <!-- gradient action icon: play triangle for video · headphones for audio -->
      <span class="mp-play" aria-hidden="true">
        <svg viewBox="0 0 64 64">
          <defs>
            <linearGradient id="mp-grad" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#ffb547"/>
              <stop offset=".55" stop-color="#ff2d6c"/>
              <stop offset="1" stop-color="#c81e7a"/>
            </linearGradient>
          </defs>
          <path v-if="!isAudio" d="M22 15.5c0-4.4 4.8-7.2 8.6-4.9l21.6 13.1c3.9 2.3 3.9 8 0 10.3L30.6 47.2c-3.8 2.3-8.6-.5-8.6-4.9v-27z" fill="url(#mp-grad)"/>
          <path v-else d="M32 10c-12.2 0-22 9.8-22 22v10a6 6 0 0 0 6 6h3a3 3 0 0 0 3-3V34a3 3 0 0 0-3-3h-3.8C16.3 21.9 23.4 16 32 16s15.7 5.9 16.8 15H45a3 3 0 0 0-3 3v11a3 3 0 0 0 3 3h3a6 6 0 0 0 6-6V32c0-12.2-9.8-22-22-22z" fill="url(#mp-grad)"/>
        </svg>
      </span>

      <span class="mp-action">{{ actionLabel }}</span>

      <span v-if="title || tags.length" class="mp-meta">
        <b v-if="title">{{ title }}</b>
        <span v-if="tags.length" class="mp-tags">
          <i v-for="t in tags" :key="t">{{ t }}</i>
        </span>
      </span>
    </button>

    <!-- ── player: same frame, overlays removed ── -->
    <template v-else>
      <iframe
        v-if="isEmbed" :src="resolved.embed" :title="title || 'Media player'"
        allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen
      ></iframe>
      <div v-else-if="isAudio" class="mp-audio">
        <span class="mp-wave live" aria-hidden="true"><i v-for="n in 7" :key="n" :style="{ animationDelay: `${n * .12}s` }"></i></span>
        <audio :src="src" controls autoplay></audio>
      </div>
      <video v-else :src="src" :poster="poster" controls autoplay playsinline></video>
    </template>
  </div>
</template>

<style scoped>
.mp { position: relative; width: 100%; aspect-ratio: 16 / 9; border-radius: 18px; overflow: hidden;
  background: var(--color-secondary); box-shadow: 0 14px 32px rgba(20, 24, 29, .14); }

/* facade */
.mp-facade { display: block; width: 100%; height: 100%; border: 0; padding: 0; background: none;
  cursor: pointer; text-align: left; }
.mp-poster { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;
  transition: transform .3s ease; }
.mp-facade:hover .mp-poster { transform: scale(1.04); }
.mp-poster-audio { display: grid; place-items: center;
  background: linear-gradient(140deg, var(--color-secondary), #3a2030); }
.mp-shade { position: absolute; inset: 0;
  background: linear-gradient(rgba(10, 12, 16, .06), rgba(10, 12, 16, .55)); }

.mp-play { position: absolute; top: 50%; left: 50%; width: 124px; height: 124px;
  transform: translate(-50%, -50%); filter: drop-shadow(0 10px 24px color-mix(in srgb, var(--color-primary) 50%, transparent));
  transition: transform .25s ease; }
.mp-facade:hover .mp-play, .mp-facade:focus-visible .mp-play { transform: translate(-50%, -50%) scale(1.14); }
.mp-play svg { width: 100%; height: 100%; }

.mp-action { position: absolute; top: .9rem; right: .9rem; background: rgba(255, 255, 255, .92);
  color: var(--color-secondary); font-size: .78rem; font-weight: 800; padding: .35rem .8rem;
  border-radius: 999px; }

.mp-meta { position: absolute; left: 0; right: 0; bottom: 0; padding: 2rem 1rem .9rem;
  display: grid; gap: .45rem; color: #fff;
  background: linear-gradient(transparent, rgba(10, 12, 16, .72)); }
.mp-meta b { font-size: .98rem; line-height: 1.3; }
.mp-tags { display: flex; flex-wrap: wrap; gap: .35rem; }
.mp-tags i { font-style: normal; font-size: .68rem; font-weight: 700; color: #fff;
  border: 1.5px solid rgba(255, 255, 255, .65); border-radius: 999px; padding: .15rem .6rem; }

/* player */
.mp iframe, .mp video { position: absolute; inset: 0; width: 100%; height: 100%; border: 0;
  display: block; background: #000; }
.mp-audio { position: absolute; inset: 0; display: grid; place-items: center; align-content: center;
  gap: 1.6rem; background: linear-gradient(140deg, var(--color-secondary), #3a2030); padding: 1.5rem; }
.mp-audio audio { width: min(420px, 90%); }

/* audio wave bars */
.mp-wave { display: flex; align-items: flex-end; gap: 5px; height: 44px; }
.mp-wave i { width: 6px; height: 30%; border-radius: 3px;
  background: linear-gradient(180deg, #ffb547, var(--color-primary)); }
.mp-wave.live i, .mp-facade:hover .mp-wave i { animation: mp-wave 1s ease-in-out infinite; }
@keyframes mp-wave { 0%, 100% { height: 25%; } 50% { height: 95%; } }
</style>
