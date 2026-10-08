<script setup lang="ts">
import { useMemberExtras } from '~/composables/useMembers'
const memberExtras = useMemberExtras()

const route = useRoute()
const members = useMembers()
const member = members.find(m => m.slug === route.params.slug)

if (!member) {
  throw createError({ statusCode: 404, statusMessage: 'Member not found', fatal: true })
}

useHead({ title: `${member.name} — CAC Blackburn` })
// the leader's own numbers (Leadership › Stats), else the shared Profile Stats
const memberStats = (member!.stats ?? []).filter(s => s?.value || s?.label)
const stats = memberStats.length ? memberStats : memberExtras.stats
// the leader's own Ministry Focus (Leadership › Ministry focus), else the shared Profile Skills
const memberFocus = (member!.focus ?? []).filter(f => f?.name)
const focus = memberFocus.length ? memberFocus : memberExtras.skills

// "Pastor Michael Adebayo" → "Michael": the name without its title / honorific
const HONORIFIC = /^(rev(erend)?|pastor|bishop|apostle|evangelist|prophet(ess)?|deacon(ess)?|elder|dr|mr|mrs|ms|miss|sis(ter)?|bro(ther)?)\.?\s+/i
const bareName = (() => {
  let n = member!.name.trim()
  const t = String(member!.title ?? '').trim()
  if (t && n.toLowerCase().startsWith(t.toLowerCase())) n = n.slice(t.length).trim()
  while (HONORIFIC.test(n)) n = n.replace(HONORIFIC, '')
  return n || member!.name
})()
const firstName = bareName.split(/\s+/)[0]

// this member's own accounts (0..n), icon/color resolved from the global
// socials data source by key; unknown keys fall back to a generic link glyph
const { socials: socialDefs, ministriesBento } = useSiteContent()
const LINK_ICON = 'M10.6 13.4a1 1 0 0 1 0-1.4l2.8-2.8a3 3 0 0 1 4.2 4.2l-2.1 2.1a1 1 0 1 1-1.4-1.4l2.1-2.1a1 1 0 1 0-1.4-1.4l-2.8 2.8a1 1 0 0 1-1.4 0zm2.8-2.8a1 1 0 0 1 0 1.4l-2.8 2.8a3 3 0 0 1-4.2-4.2l2.1-2.1a1 1 0 1 1 1.4 1.4l-2.1 2.1a1 1 0 1 0 1.4 1.4l2.8-2.8a1 1 0 0 1 1.4 0z'
const memberSocials = (member!.socials ?? []).filter(s => s?.href).map(s => {
  const def = socialDefs.find(d => d.key === s.key)
  return { ...s, name: def?.name ?? s.key, icon: def?.icon ?? LINK_ICON, color: def?.color }
})

// the ministries they lead, linked to their pages when the Ministries menu knows them
const norm = (v: unknown) => String(v ?? '').toLowerCase().replace(/[^a-z0-9]/g, '')
const ministries = (Array.isArray(member!.ministries) ? member!.ministries : String(member!.ministries ?? '').split(','))
  .map(x => String(x).trim()).filter(Boolean)
  .map((label) => {
    const page = ministriesBento.find(m => [m.title, m.altName, m.to].some(k => k && norm(k) === norm(label)))
    return { label, to: page?.to ?? '' }
  })

// the rest of the team (up to 3)
const others = members.filter(m => m.slug !== member!.slug).slice(0, 3)
</script>

<template>
  <div v-if="member" class="profile-page">
    <SiteHeader />

    <PageHeroContent :crumbs="[{ label: 'About Us', to: '/about' }, { label: 'Leadership', to: '/leadership' }, { label: member.name }]">
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
            ><svg viewBox="0 0 24 24" fill="currentColor"><path :d="s.icon" /></svg></a>
          </div>
          <h1>Hi, I'm <span class="hl">{{ firstName }}</span></h1>
          <p class="profile-role">{{ member.role }}</p>
          <p class="profile-intro about-text">{{ member.bio }}</p>
          <div class="profile-actions">
            <a class="btn" href="#profile-form"><EventIcon name="mail" :size="17" class="pf-btn-icon" /> Contact Me</a>
            <NuxtLink class="btn ghost" to="/leadership">← All Leaders</NuxtLink>
          </div>
        </div>

        <div class="profile-hero-photo">
          <img v-if="member.img" :src="member.img" :alt="`Portrait of ${member.name}`">
          <span v-else class="pf-initials">{{ bareName.split(/\s+/).slice(0, 2).map(w => w[0]).join('') }}</span>
          <span class="float-badge fb-1">✝</span>
          <span class="float-badge fb-2">📖</span>
          <span class="float-badge fb-3">🙏</span>
        </div>

        <!-- About: verse card + stats -->
        <div class="profile-about">
          <h2>About <span class="hl">{{ firstName }}</span></h2>
          <div class="about-card">
            <blockquote v-if="member.verse">{{ member.verse }}</blockquote>
            <p class="about-text">{{ member.about || member.bio }}</p>
          </div>
          <div v-if="stats.length" class="profile-stats" :data-olx-panel="memberStats.length ? 'leadership' : 'profile-stats'">
            <div v-for="s in stats" :key="s.label" data-olx-item>
              <b>{{ s.value }}</b>
              <span>{{ s.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- a little more: fun fact · ministries led -->
    <section v-if="member.fact || ministries.length" class="pf-more">
      <div class="container pf-more-row">
        <div v-if="member.fact" class="pf-side-card pf-fact">
          <p class="pf-side-title"><EventIcon name="star" :size="16" /> Fun fact</p>
          <p>{{ member.fact }}</p>
        </div>
        <div v-if="ministries.length" class="pf-side-card">
          <p class="pf-side-title"><EventIcon name="users" :size="16" /> Ministries led</p>
          <ul class="pf-chips">
            <li v-for="m in ministries" :key="m.label">
              <NuxtLink v-if="m.to" :to="m.to">{{ m.label }} <EventIcon name="arrow" :size="13" /></NuxtLink>
              <span v-else>{{ m.label }}</span>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- ministry focus -->
    <section v-if="focus.length" class="profile-skills">
      <div class="container">
        <div class="section-head center">
          <h2>Ministry <span class="hl">Focus</span></h2>
          <p>The areas of service where {{ firstName }} leads and equips others.</p>
        </div>
        <div class="skills-grid" :data-olx-panel="memberFocus.length ? 'leadership' : 'profile-skills'">
          <div v-for="sk in focus" :key="sk.name" data-olx-item class="skill-card">
            <div class="skill-head">
              <span class="skill-icon">{{ sk.icon }}</span>
              <b>{{ sk.name }}</b>
            </div>
            <span class="skill-check" aria-hidden="true">✓</span>
          </div>
        </div>
      </div>
    </section>

    <!-- get in touch -->
    <section id="profile-form" class="profile-contact-sec">
      <div class="container">
        <div class="section-head center">
          <h2>Get In <span class="hl">Touch</span></h2>
          <p>Send {{ firstName }} a message — every note gets a reply.</p>
        </div>
        <LeaderContactForm :member="member.name" :first-name="firstName" />
      </div>
    </section>

    <!-- the rest of the team -->
    <section v-if="others.length" class="pf-team">
      <div class="container">
        <div class="pf-team-head">
          <div>
            <p class="pf-kicker">Leadership</p>
            <h2>Meet the rest of the team</h2>
          </div>
          <NuxtLink class="btn ghost" to="/leadership">All leaders</NuxtLink>
        </div>
        <div class="pf-team-grid">
          <NuxtLink v-for="m in others" :key="m.slug" :to="`/leadership/${m.slug}`" class="pf-mini">
            <span class="pf-mini-photo">
              <img v-if="m.img" :src="m.img" :alt="`Portrait of ${m.name}`" loading="lazy">
            </span>
            <span class="pf-mini-text"><b>{{ m.name }}</b><small>{{ m.role }}</small></span>
            <EventIcon name="arrow" :size="16" class="pf-mini-go" />
          </NuxtLink>
        </div>
      </div>
    </section>

    <SiteFooter />
  </div>
</template>

<style scoped>
.about-text { white-space: pre-line; }
.pf-more .container, .pf-team .container { max-width: var(--container-width); }

/* hero: copy · portrait · about — the two text sections flank the centred photo */
.profile-tri-sec { padding-bottom: 44px; }
.profile-hero .container.profile-tri { display: grid; grid-template-columns: 1fr minmax(280px, 360px) 1fr; gap: 2.5rem; align-items: center; }
.profile-tri .profile-hero-photo { flex: none; width: 100%; }
.profile-tri .profile-about { padding: 0; }
.profile-tri .profile-about h2 { font-size: 1.5rem; margin-bottom: 1rem; }
.profile-tri .about-card { max-width: none; margin: 0; padding: 1.5rem 1.6rem; }
.profile-tri .about-card p { color: #2b333d; font-size: 17px; line-height: 1.6; }
.profile-tri .profile-stats { justify-content: flex-start; gap: 2.2rem; margin-top: 1.6rem; text-align: left; }
.profile-tri .profile-stats span { color: #2b333d; }
.pf-initials { display: grid; place-items: center; aspect-ratio: 1; border-radius: 26px; font-family: var(--font-heading); font-size: 4rem; color: #fff;
  background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 35%, var(--color-secondary))); }
.pf-btn-icon { display: inline-block; vertical-align: -3px; margin-right: .2rem; }
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

/* a little more: fun fact · ministries led */
.pf-more { padding: 0 0 3rem; }
.pf-more-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 1rem; }
.pf-side-card { border-radius: 20px; padding: 1.2rem 1.3rem; background: #fff; box-shadow: 0 12px 30px rgba(20, 24, 29, .07); }
.pf-side-title { display: flex; align-items: center; gap: .45rem; font-weight: 800; font-size: .82rem; letter-spacing: .1em; text-transform: uppercase;
  color: var(--color-primary); margin-bottom: .55rem; }
.pf-side-card p:not(.pf-side-title) { color: #4b5560; line-height: 1.6; }
.pf-fact { background: var(--primary-soft); box-shadow: none; }
.pf-chips { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; gap: .45rem; }
.pf-chips a, .pf-chips span { display: inline-flex; align-items: center; gap: .3rem; padding: .35rem .85rem; border-radius: 999px; background: #fff;
  border: 1.5px solid #ece5da; font-weight: 700; font-size: .9rem; color: var(--color-secondary); }
.pf-chips a:hover { border-color: var(--color-primary); color: var(--color-primary); }

.pf-kicker { font-size: .8rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--color-primary); margin-bottom: .5rem; }

/* rest of the team */
.pf-team { padding: 1rem 0 5rem; }
.pf-team-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.6rem; }
.pf-team-head h2 { font-size: clamp(1.7rem, 3vw, 2.2rem); }
.pf-team-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); gap: 1rem; }
.pf-mini { display: flex; align-items: center; gap: .9rem; background: #fff; border-radius: 20px; padding: .8rem 1rem; color: inherit;
  box-shadow: 0 12px 30px rgba(20, 24, 29, .07); transition: transform .2s ease; }
.pf-mini:hover { transform: translateY(-3px); }
.pf-mini-photo { width: 64px; height: 64px; flex: none; border-radius: 50%; overflow: hidden;
  background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 35%, var(--color-secondary))); }
.pf-mini-photo img { width: 100%; height: 100%; object-fit: cover; object-position: top; }
.pf-mini-text { flex: 1; min-width: 0; }
.pf-mini-text b { display: block; color: var(--color-secondary); font-size: 1.05rem; line-height: 1.3; }
.pf-mini-text small { display: block; color: #6b737c; font-size: .86rem; }
.pf-mini-go { color: #c9c2b8; }
.pf-mini:hover .pf-mini-go { color: var(--color-primary); }
.pf-mini:hover b { color: var(--color-primary); }
</style>
