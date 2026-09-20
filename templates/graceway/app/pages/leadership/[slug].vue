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
          <div class="profile-socials">
            <a href="#" aria-label="YouTube"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M23 7.2s-.2-1.6-.9-2.3c-.9-.9-1.9-.9-2.3-1C16.6 3.6 12 3.6 12 3.6s-4.6 0-7.8.3c-.4.1-1.4.1-2.3 1-.7.7-.9 2.3-.9 2.3S.8 9.1.8 11v1.8c0 1.9.2 3.8.2 3.8s.2 1.6.9 2.3c.9.9 2 .9 2.5 1 1.8.2 7.6.3 7.6.3s4.6 0 7.8-.4c.4-.1 1.4-.1 2.3-1 .7-.7.9-2.3.9-2.3s.2-1.9.2-3.8V11c0-1.9-.2-3.8-.2-3.8zM9.9 15.1V8.4l6.2 3.4-6.2 3.3z"/></svg></a>
            <a href="#" aria-label="X"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.2 2.3h3.3l-7.3 8.3L22.8 22h-6.7l-5.3-6.9L4.8 22H1.5l7.8-8.9L1.1 2.3H8l4.8 6.3 5.4-6.3zm-1.2 17.7h1.8L7 4.2H5l12 15.8z"/></svg></a>
            <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-9h3l.5-3.5h-3.5V7.2c0-1 .3-1.7 1.8-1.7H17V2.2c-.3 0-1.4-.2-2.6-.2-2.6 0-4.4 1.6-4.4 4.5v3H7V13h3v9h3.5z"/></svg></a>
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
