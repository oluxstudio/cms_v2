<script setup lang="ts">
const oluxCms = useOluxContent('join-church')
const oluxFb: Record<string, string> = {}
const { join } = useSiteContent()
const { steps, perks, interests, faqs } = join
const open = ref<number | null>(0)

const submitted = ref(false)
const { submit: cmsSubmit, sending, error: sendError } = useCmsForm('join-church')
const sendForm = async (e: Event) => {
  const data = Object.fromEntries(new FormData(e.target as HTMLFormElement).entries())
  if (await cmsSubmit(data)) submitted.value = true
}
</script>

<template>
  <section class="join-sec" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container">
      <!-- the journey
      <div class="section-head center">
        <p class="eyebrow">{{ join.stepsEyebrow }}</p>
        <h2>{{ join.stepsTitle }}</h2>
      </div>
      <div class="join-steps">
        <article v-for="s in steps" :key="s.n" class="join-step">
          <span class="join-step-n">{{ s.n }}</span>
          <h3>{{ s.title }}</h3>
          <p>{{ s.text }}</p>
        </article>
      </div> -->

      <!-- perks + form -->
      <div class="join-grid">
        <div class="ct-info join-perks">
          <h3>{{ join.perksTitle }}</h3>
          <ul>
            <li v-for="p in perks" :key="p"><i aria-hidden="true">✓</i><span>{{ p }}</span></li>
          </ul>
          <div class="join-note">
            <b>{{ join.note.title }}</b>
            <p>{{ join.note.text }}</p>
          </div>
        </div>

        <div class="ct-form-panel join-form-panel">
          <span class="ct-chip">{{ join.chip }}</span>
          <h2>{{ join.title }}</h2>
          <p class="ct-sub">{{ join.sub }}</p>

          <form v-if="!submitted" class="ct-form" @submit.prevent="sendForm">
            <div class="row">
              <input type="text" name="name" placeholder="Full name" required>
              <input type="email" name="email" placeholder="Email address" required>
            </div>
            <div class="row">
              <input type="tel" name="phone" placeholder="Phone (optional)">
              <select name="interest">
                <option value="" disabled selected>{{ join.interestPlaceholder }}</option>
                <option v-for="i in interests" :key="i">{{ i }}</option>
              </select>
            </div>
            <textarea name="message" placeholder="Anything you'd like us to know? (optional)"></textarea>
            <label class="join-check"><input type="checkbox" name="visit" value="yes"> I'd like someone to contact me about visiting</label>
            <label class="join-check"><input type="checkbox" name="lunch" value="yes"> Save me a seat at the next newcomers' lunch</label>
            <p v-if="sendError" class="form-error">{{ sendError }}</p>
            <button class="btn ct-send" type="submit" :disabled="sending">{{ sending ? 'Sending…' : 'Join the Church' }} <span class="arrow">↗</span></button>
            <p class="join-fine">{{ join.fine }}</p>
          </form>

          <div v-else class="ct-thanks">
            <p>{{ join.thanks }}</p>
            <NuxtLink class="btn" to="/events">See What's On</NuxtLink>
          </div>
        </div>
      </div>

      <!-- FAQs -->
      <div class="section-head center join-faq-head">
        <p class="eyebrow">{{ join.faqEyebrow }}</p>
        <h2>{{ join.faqTitle }}</h2>
      </div>
      <div class="join-faqs">
        <div v-for="(f, i) in faqs" :key="f.q" class="join-faq" :class="{ open: open === i }">
          <button type="button" :aria-expanded="open === i" @click="open = open === i ? null : i">
            {{ f.q }} <span aria-hidden="true">{{ open === i ? '−' : '+' }}</span>
          </button>
          <p v-show="open === i">{{ f.a }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.join-sec { padding: 3rem 0 4rem; }

/* journey steps */
.join-steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.8rem; margin-top: 2rem; }
.join-step { background: #fff; border-radius: 20px; padding: 1.8rem; box-shadow: 0 14px 32px rgba(20, 24, 29, .08); }
.join-step-n { font-family: var(--font-heading); font-size: 2rem; color: var(--color-primary); display: block; margin-bottom: .6rem; }
.join-step h3 { font-size: 1.15rem; color: var(--color-secondary); margin-bottom: .4rem; }
.join-step p { font-size: 17px; color: #2b333d; line-height: 1.6; }

/* perks + form — shared card rhythm */
.join-grid { display: grid; grid-template-columns: .8fr 1.2fr; gap: 1.8rem; align-items: start; max-width: 1080px; margin: 0 auto; }
.join-perks { position: sticky; top: 1.5rem; padding: 2.2rem; }
.join-perks h3 { font-size: 1.15rem; margin-bottom: 1.2rem; }
.join-perks ul { list-style: none; padding: 0; display: grid; }
.join-perks li { display: flex; align-items: flex-start; gap: .9rem; font-size: 17px; color: #2b333d; line-height: 1.55;
  padding: .75rem 0; border-bottom: 1px solid #f1ece3; }
.join-perks li:last-child { border-bottom: 0; }
.join-perks li i { flex: none; width: 26px; height: 26px; border-radius: 50%; background: #fdeef3; color: var(--color-primary);
  font-style: normal; font-weight: 800; font-size: .85rem; display: grid; place-items: center; margin-top: .1rem; }
.join-note { margin-top: 1.4rem; padding: 1.2rem 1.3rem; background: #fdeef3; border-radius: 14px;
  border-left: 4px solid var(--color-primary); }
.join-note b { display: block; font-size: 1rem; color: var(--color-secondary); margin-bottom: .3rem; }
.join-note p { font-size: 17px; color: #2b333d; line-height: 1.55; }

.join-form-panel { padding: 2.2rem; }
.join-form-panel .ct-chip { font-size: 1.15rem; }
.join-form-panel h2 { margin: .8rem 0 .5rem; }
.join-form-panel :deep(.ct-sub) { font-size: 17px; margin-bottom: 1.4rem; color: #2b333d; }
.join-form-panel :deep(.ct-thanks p) { color: #2b333d; }
.join-form-panel :deep(input), .join-form-panel :deep(select), .join-form-panel :deep(textarea) { color: #14181d; }
.join-form-panel :deep(input::placeholder), .join-form-panel :deep(textarea::placeholder) { color: #4d5661; }
.join-form-panel .ct-form { display: grid; gap: 1rem; }
.join-check { display: flex; align-items: center; gap: .6rem; font-size: 17px; color: #2b333d; margin: 0; }
.join-check + .join-check { margin-top: -.4rem; }
.join-check input { width: 18px; height: 18px; accent-color: var(--color-primary); }
.join-fine { font-size: .8rem; color: #4d5661; text-align: center; margin-top: .2rem; }

/* faqs */
.join-faq-head { margin-top: 3.5rem; }
.join-faqs { max-width: 760px; margin: 1.6rem auto 0; display: grid; gap: 1rem; }
.join-faq { background: #fff; border-radius: 16px; box-shadow: 0 10px 24px rgba(20, 24, 29, .07); overflow: hidden; }
.join-faq button { display: flex; justify-content: space-between; align-items: center; gap: 1rem; width: 100%;
  font: inherit; font-size: 1.05rem; font-weight: 700; color: var(--color-secondary); text-align: left;
  background: none; border: 0; cursor: pointer; padding: 1.1rem 1.4rem; }
.join-faq button span { font-size: 1.3rem; color: var(--color-primary); flex: none; }
.join-faq p { font-size: 17px; color: #2b333d; line-height: 1.65; padding: 0 1.4rem 1.2rem; }
.join-faq.open { box-shadow: 0 14px 32px rgba(20, 24, 29, .1); }

@media (max-width: 1000px) { .join-steps { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 900px) { .join-grid { grid-template-columns: 1fr; } }
@media (max-width: 600px) { .join-steps { grid-template-columns: 1fr; } }
</style>
