<script setup lang="ts">
const oluxCms = useOluxContent('site-footer')
const oluxFb: Record<string, string> = {"Subheadline":"Explore","CTA Label":"About Us","CTA Link":"/about","CTA B Label":"Ministries","CTA B Link":"/ministries","CTA C Label":"Sermons","CTA C Link":"/sermons","CTA D Label":"Events","CTA D Link":"/events","CTA E Label":"Give","CTA E Link":"/donate","CTA F Label":"Contact","CTA F Link":"/contact","Subheadline B":"Service Times","Subheadline C":"Visit Us"}
// identity, contact details, service times and socials come from the global data source
const { profile, services, socials } = useSiteContent()
const sundayTimes = services.filter(s => s.day === 'Sunday').map(s => s.time.replace(':00 ', ' ')).join(' and ')
const followable = socials.filter(s => s.available && s.key !== 'zoom')
</script>

<template>
  <footer class="site-footer" v-if="!oluxCms.hidden()" :style="oluxCms.rootStyle.value" :class="oluxCms.rootClass.value">
    <div class="container footer-grid">
      <!-- identity + socials -->
      <div>
        <a class="logo" href="/">{{ profile.logo.lead }} <b>{{ profile.logo.bold }}</b></a>
        <p class="footer-blurb">A church for the whole community — worship with us Sundays at {{ sundayTimes }}.</p>
        <div class="footer-social">
          <a
            v-for="s in followable" :key="s.key" :href="s.href"
            target="_blank" rel="noopener" :aria-label="s.name" :title="s.name"
          >
            <svg viewBox="0 0 24 24" fill="currentColor"><path :d="s.icon"/></svg>
          </a>
        </div>
      </div>

      <!-- site links -->
      <div>
        <h4 data-olx-field="subheadline">{{ oluxCms.t('Subheadline', oluxFb['Subheadline']) }}</h4>
        <a data-olx-field="ctaLabel" :href="oluxCms.t('CTA Link', oluxFb['CTA Link'])">{{ oluxCms.t('CTA Label', oluxFb['CTA Label']) }}</a>
        <a data-olx-field="ctaBLabel" :href="oluxCms.t('CTA B Link', oluxFb['CTA B Link'])">{{ oluxCms.t('CTA B Label', oluxFb['CTA B Label']) }}</a>
        <a data-olx-field="ctaCLabel" :href="oluxCms.t('CTA C Link', oluxFb['CTA C Link'])">{{ oluxCms.t('CTA C Label', oluxFb['CTA C Label']) }}</a>
        <a data-olx-field="ctaDLabel" :href="oluxCms.t('CTA D Link', oluxFb['CTA D Link'])">{{ oluxCms.t('CTA D Label', oluxFb['CTA D Label']) }}</a>
        <a data-olx-field="ctaELabel" :href="oluxCms.t('CTA E Link', oluxFb['CTA E Link'])">{{ oluxCms.t('CTA E Label', oluxFb['CTA E Label']) }}</a>
        <a data-olx-field="ctaFLabel" :href="oluxCms.t('CTA F Link', oluxFb['CTA F Link'])">{{ oluxCms.t('CTA F Label', oluxFb['CTA F Label']) }}</a>
      </div>

      <!-- service times -->
      <div>
        <h4 data-olx-field="subheadlineB">{{ oluxCms.t('Subheadline B', oluxFb['Subheadline B']) }}</h4>
        <p v-for="s in services" :key="s.label + s.time" class="footer-line">
          {{ s.label }}<br><span>{{ s.day }} · {{ s.time }}</span>
        </p>
      </div>

      <!-- visit / contact -->
      <div>
        <h4 data-olx-field="subheadlineC">{{ oluxCms.t('Subheadline C', oluxFb['Subheadline C']) }}</h4>
        <p class="footer-line">
          {{ profile.address }}<br>
          <span>{{ profile.city }} {{ profile.postalCode }}, {{ profile.country }}</span>
        </p>
        <a :href="profile.phoneHref">{{ profile.phone }}</a>
        <a :href="`mailto:${profile.email}`">{{ profile.email }}</a>
        <p v-for="o in profile.officeHours" :key="o.days" class="footer-line office">
          Office: {{ o.days }} · {{ o.hours }}
        </p>
      </div>

      <p class="fine">
        {{ profile.copyright }}
        <span class="legal-links">
          <NuxtLink to="/privacy-policy">Privacy Policy</NuxtLink> ·
          <NuxtLink to="/terms">Terms &amp; Conditions</NuxtLink> ·
          <NuxtLink to="/cookie-policy">Cookie Policy</NuxtLink>
        </span>
      </p>
    </div>
  </footer>
</template>

<style scoped>
/* beats the global .site-footer .container grid rule */
.site-footer .footer-grid { grid-template-columns: 1.6fr 1fr 1.1fr 1.3fr; }
.footer-blurb { margin-top: .8rem; font-size: .9rem; max-width: 320px; }

.footer-social { display: flex; gap: .6rem; margin-top: 1.1rem; }
.footer-social a { display: grid; place-items: center; width: 36px; height: 36px; padding: 0;
  border-radius: 10px; background: rgba(255, 255, 255, .08); color: #fff; transition: background .2s, transform .2s; }
.footer-social a:hover { background: var(--color-primary); color: #fff; transform: translateY(-2px); }
.footer-social svg { width: 17px; height: 17px; }

.footer-line { font-size: .95rem; padding: .18rem 0; }
.footer-line span { color: #7f8791; font-size: .88rem; }
.footer-line.office { margin-top: .4rem; font-size: .88rem; color: #7f8791; }

.fine { grid-column: 1 / -1; }
.legal-links { display: block; margin-top: .4rem; }
.legal-links a { display: inline; padding: 0 .2rem; }
.legal-links a:hover { color: var(--color-primary); }

@media (max-width: 900px) { .site-footer .footer-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 720px) { .site-footer .footer-grid { grid-template-columns: 1fr; } }
</style>
