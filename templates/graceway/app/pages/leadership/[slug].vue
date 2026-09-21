<script setup lang="ts">
import { memberExtras } from '~/composables/useMembers'

const route = useRoute()
const members = useMembers()
const member = members.find(m => m.slug === route.params.slug)

if (!member) {
  throw createError({ statusCode: 404, statusMessage: 'Member not found', fatal: true })
}

useHead({ title: `${member.name} — CAC Blackburn` })
const firstName = member!.name.split(' ').pop()

// this member's own accounts (0..n), icon/color resolved from the global
// socials data source by key; unknown keys fall back to a generic link glyph
const { socials: socialDefs } = useSiteContent()
const LINK_ICON = 'M10.6 13.4a1 1 0 0 1 0-1.4l2.8-2.8a3 3 0 0 1 4.2 4.2l-2.1 2.1a1 1 0 1 1-1.4-1.4l2.1-2.1a1 1 0 1 0-1.4-1.4l-2.8 2.8a1 1 0 0 1-1.4 0zm2.8-2.8a1 1 0 0 1 0 1.4l-2.8 2.8a3 3 0 0 1-4.2-4.2l2.1-2.1a1 1 0 1 1 1.4 1.4l-2.1 2.1a1 1 0 1 0 1.4 1.4l2.8-2.8a1 1 0 0 1 1.4 0z'
const memberSocials = (member!.socials ?? []).map(s => {
  const def = socialDefs.find(d => d.key === s.key)
  return { ...s, name: def?.name ?? s.key, icon: def?.icon ?? LINK_ICON, color: def?.color }
})
</script>

<template>
  <div v-if="member" class="profile-page">
    <SiteHeader />

    <!-- page hero banner — same style as the About page hero -->
    <PageHeroContent :crumbs="[{ label: 'About Us', to: '/about' }, { label: 'Leadership', to: '/about#leadership' }, { label: member.name }]">
      <p class="eyebrow">Leadership</p>
      <h1>{{ member.name }}</h1>
      <p>{{ member.role }} — serving the CAC Blackburn family.</p>
    </PageHeroContent>

    <!-- Hero: intro left · portrait centre · about right -->
    <section class="profile-hero profile-tri-sec">
      <div class="container profile-tri">
        <div class="profile-hero-copy">
          <div v-if="memberSocials.length" class="profile-socials">
            <a
              v-for="s in memberSocials" :key="s.key + s.href" :href="s.href"
              target="_blank" rel="noopener" :aria-label="s.name" :title="s.name"
            ><svg viewBox="0 0 24 24" fill="currentColor"><path :d="s.icon"/></svg></a>
          </div>
          <h1>Hi, I'm <span class="hl">{{ firstName }}</span></h1>
          <p class="profile-role">{{ member.role }}</p>
          <p class="profile-intro">{{ member.bio }}</p>
          <div class="profile-actions">
            <a class="btn" href="#profile-form">✉ Contact Me</a>
            <NuxtLink class="btn ghost" to="/about#leadership">← All Leaders</NuxtLink>
          </div>
        </div>

        <div class="profile-hero-photo">
          <img :src="member.img" :alt="`Portrait of ${member.name}`">
          <span class="float-badge fb-1">✝</span>
          <span class="float-badge fb-2">📖</span>
          <span class="float-badge fb-3">🙏</span>
        </div>

        <!-- About: verse card + stats -->
        <div class="profile-about">
          <h2>About <span class="hl">{{ firstName }}</span></h2>
          <div class="about-card">
            <blockquote>{{ member.verse }}</blockquote>
            <p>{{ member.bio }}</p>
          </div>
          <div class="profile-stats">
            <div v-for="s in memberExtras.stats" :key="s.label">
              <b>{{ s.value }}</b>
              <span>{{ s.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Ministry focus: skill cards with proficiency bars -->
    <section class="profile-skills">
      <div class="container">
        <div class="section-head center">
          <h2>Ministry <span class="hl">Focus</span></h2>
          <p>The areas of service where {{ firstName }} leads and equips others.</p>
        </div>
        <div class="skills-grid">
          <div v-for="sk in memberExtras.skills" :key="sk.name" class="skill-card">
            <div class="skill-head">
              <span class="skill-icon">{{ sk.icon }}</span>
              <b>{{ sk.name }}</b>
            </div>
            <span class="skill-check" aria-hidden="true">✓</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Get in touch -->
    <section id="profile-form" class="profile-contact-sec">
      <div class="container">
        <div class="section-head center">
          <h2>Get In <span class="hl">Touch</span></h2>
          <p>Send {{ firstName }} a message — every note gets a reply.</p>
        </div>
        <form class="profile-form" @submit.prevent>
          <div class="row">
            <input type="text" name="first" placeholder="First Name" required>
            <input type="text" name="last" placeholder="Last Name">
          </div>
          <input type="email" name="email" placeholder="Email Address" required>
          <textarea name="message" placeholder="Your Message" required></textarea>
          <button class="btn" type="submit">Send Message</button>
        </form>
      </div>
    </section>

    <SiteFooter />
  </div>
</template>

<style scoped>
/* hero: copy · portrait · about — the two text sections flank the centred photo */
.profile-tri-sec { padding-bottom: 44px; }
.profile-hero .container.profile-tri { display: grid; grid-template-columns: 1fr minmax(280px, 360px) 1fr;
  gap: 2.5rem; align-items: center; }

.profile-tri .profile-hero-photo { flex: none; width: 100%; }

.profile-tri .profile-about { padding: 0; }
.profile-tri .profile-about h2 { font-size: 1.5rem; margin-bottom: 1rem; }
.profile-tri .about-card { max-width: none; margin: 0; padding: 1.5rem 1.6rem; }
.profile-tri .about-card p { color: #2b333d; font-size: 17px; line-height: 1.6; }
.profile-tri .profile-stats { justify-content: flex-start; gap: 2.2rem; margin-top: 1.6rem; text-align: left; }
.profile-tri .profile-stats span { color: #2b333d; }

/* tablet: the two text sections become the 2 columns, portrait spans above them */
@media (max-width: 1180px) {
  .profile-hero .container.profile-tri { grid-template-columns: 1fr 1fr; }
  .profile-tri .profile-hero-photo { grid-column: 1 / -1; max-width: 380px; margin: 0 auto; grid-row: 1; }
}

/* mobile: single column — portrait, then intro, then about */
@media (max-width: 760px) {
  .profile-hero .container.profile-tri { grid-template-columns: 1fr; gap: 2rem; }
  .profile-tri .profile-hero-photo { grid-row: 1; }
  .profile-tri .profile-stats { justify-content: center; text-align: center; }
}
</style>
