<script setup lang="ts">
// One event's own page: everything about it (when, where, entry, who's
// speaking, guests, the full description) beside the RSVP form, so visitors
// read about the event while they book. Cards elsewhere only summarise it.
// Past events point to their archive gallery instead of booking.
const route = useRoute()
const rows = computed(() => useEventRows())
const event = computed(() => rows.value.find(e => e.id === String(route.params.id)))
const past = computed(() => !!event.value && eventIsPast(event.value))
const more = computed(() => upcomingEvents(rows.value).filter(e => e.id !== event.value?.id).slice(0, 3))

useHead({ title: computed(() => event.value ? `${event.value.title} — CAC Blackburn` : 'Event — CAC Blackburn') })

const { reserved } = useEventReservations()
const day = computed(() => eventDayLabel(event.value?.date))
const time = computed(() => eventTimeLabel(event.value?.date))
const place = computed(() => eventPlace(event.value?.location))
const type = computed(() => eventTypeLabel(event.value?.ministry))
const entry = computed(() => !event.value ? '' : event.value.price > 0 ? `£${event.value.price} per seat` : past.value ? 'Free' : 'Free — RSVP to save your seat')
const seats = computed(() => !event.value || past.value || event.value.seatsLeft === '' ? '' : event.value.seatsLeft > 0 ? `${event.value.seatsLeft} seats left` : 'Fully booked')
const directions = computed(() => event.value?.location ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(event.value.location.replace(/\n+/g, ', '))}` : '')
const initials = (name: string) => name.replace(/\(.*?\)/g, '').replace(/^(Rev|Pastor|Dr|Mr|Mrs|Ms|Miss)\.?\s+/i, '').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]?.toUpperCase()).join('') || '★'
const broken = ref(false)

// Add to calendar: an .ics file (2 hours unless the event says otherwise)
const addToCalendar = () => {
  const e = event.value
  const start = e?.date ? new Date(e.date) : null
  if (!e || !start || Number.isNaN(start.getTime())) return
  const stamp = (d: Date) => d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '')
  const esc = (s: string) => s.replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/[,;]/g, m => `\\${m}`)
  const ics = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Church events//EN', 'BEGIN:VEVENT',
    `UID:${e.id}@${location.host}`, `DTSTAMP:${stamp(new Date())}`, `DTSTART:${stamp(start)}`, `DTEND:${stamp(new Date(start.getTime() + 2 * 3600_000))}`,
    `SUMMARY:${esc(e.title)}`, `DESCRIPTION:${esc(`${e.text}\n\n${location.href}`)}`, ...(e.location ? [`LOCATION:${esc(e.location)}`] : []),
    'END:VEVENT', 'END:VCALENDAR'].join('\r\n')
  const url = URL.createObjectURL(new Blob([ics], { type: 'text/calendar' }))
  const a = Object.assign(document.createElement('a'), { href: url, download: `${e.id}.ics` })
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

// Share: the phone's share sheet, else copy the link
const shared = ref(false)
const share = async () => {
  if (!event.value) return
  try {
    if (navigator.share) { await navigator.share({ title: event.value.title, url: location.href }); return }
    await navigator.clipboard.writeText(location.href)
    shared.value = true
    setTimeout(() => { shared.value = false }, 2500)
  } catch { /* dismissed */ }
}
</script>

<template>
  <div>
    <SiteHeader />

    <PageHeroContent :crumbs="[{ label: 'Events', to: '/events' }, { label: event?.title ?? 'Event' }]">
      <p class="eyebrow">{{ !event ? 'Events' : `${type} · ${past ? 'Past event' : 'Upcoming event'}` }}</p>
      <h1>{{ event?.title ?? 'Event not found' }}</h1>
      <ul v-if="event" class="evp-chips">
        <li><EventIcon name="calendar" :size="17" />{{ day }}</li>
        <li v-if="time"><EventIcon name="clock" :size="17" />{{ time }}</li>
        <li v-if="place"><EventIcon name="pin" :size="17" />{{ place }}</li>
        <li><EventIcon name="ticket" :size="17" />{{ eventPriceLabel(event.price) }}</li>
      </ul>
      <div v-if="event" class="evp-hero-actions">
        <a v-if="!past" class="btn" href="#reserve">Reserve your seat</a>
        <button v-if="!past" type="button" class="evp-ghost" @click="addToCalendar"><EventIcon name="calendar-plus" :size="18" /> Add to calendar</button>
        <a v-if="directions" class="evp-ghost" :href="directions" target="_blank" rel="noopener"><EventIcon name="directions" :size="17" /> Get directions</a>
      </div>
    </PageHeroContent>

    <section v-if="event" class="evp">
      <div class="container evp-layout">
        <div class="evp-main">
          <!-- the poster -->
          <div class="evp-poster" :class="{ 'is-plain': !event.img || broken }">
            <img v-if="event.img && !broken" :src="event.img" :alt="event.title" @error="broken = true">
            <div v-else class="evp-poster-plain">
              <EventIcon name="calendar" :size="72" />
              <span>{{ type }}</span>
            </div>
            <span v-if="eventBadge(event.date)" class="evp-badge">
              <b>{{ eventBadge(event.date)!.day }}</b><span>{{ eventBadge(event.date)!.month }}</span>
            </span>
          </div>

          <div class="evp-block">
            <p class="evp-kicker">About this event</p>
            <p class="evp-text">{{ event.text || 'More details coming soon.' }}</p>
          </div>

          <!-- the essentials -->
          <div class="evp-block">
            <p class="evp-kicker">Event details</p>
            <dl class="evp-facts">
              <div class="evp-fact">
                <dt><span class="evp-icon"><EventIcon name="calendar" /></span>When</dt>
                <dd>{{ day }}</dd>
                <dd v-if="time" class="evp-sub">{{ time }}</dd>
              </div>
              <div v-if="event.location" class="evp-fact">
                <dt><span class="evp-icon"><EventIcon name="pin" /></span>Location</dt>
                <dd class="evp-multiline">{{ event.location }}</dd>
                <a v-if="directions" class="evp-link" :href="directions" target="_blank" rel="noopener">Get directions <EventIcon name="arrow" :size="15" /></a>
              </div>
              <div class="evp-fact">
                <dt><span class="evp-icon"><EventIcon name="ticket" /></span>Entry</dt>
                <dd>{{ entry }}</dd>
                <dd v-if="seats" class="evp-sub">{{ seats }}</dd>
              </div>
              <div class="evp-fact">
                <dt><span class="evp-icon"><EventIcon name="users" /></span>Event type</dt>
                <dd>{{ type }}</dd>
              </div>
            </dl>
          </div>

          <!-- who's leading, who's joining -->
          <div v-if="event.speakers?.length || event.guests?.length" class="evp-block">
            <p class="evp-kicker">Who's taking part</p>
            <ul class="evp-people">
              <li v-for="s in event.speakers" :key="`s-${s}`">
                <span class="evp-avatar">{{ initials(s) }}</span>
                <span><b>{{ s }}</b><small><EventIcon name="mic" :size="13" /> Speaker</small></span>
              </li>
              <li v-for="g in event.guests" :key="`g-${g}`">
                <span class="evp-avatar guest">{{ initials(g) }}</span>
                <span><b>{{ g }}</b><small><EventIcon name="star" :size="13" /> Special guest</small></span>
              </li>
            </ul>
          </div>

          <ul v-if="event.tags?.length" class="evp-tags">
            <li v-for="t in event.tags" :key="t"><EventIcon name="tag" :size="13" />{{ t }}</li>
          </ul>
        </div>

        <!-- booking -->
        <aside class="evp-aside">
          <div id="reserve" class="evp-card">
            <EventRsvp v-if="!past" :event="event" />
            <template v-else>
              <p class="eyebrow">This event has ended</p>
              <h3>Thanks to everyone who came</h3>
              <p v-if="reserved[event.id]" class="evp-note">You reserved {{ reserved[event.id] }} {{ reserved[event.id] === 1 ? 'seat' : 'seats' }} for this one.</p>
              <NuxtLink v-if="event.media?.length" class="btn" :to="`/event-archive/${event.id}`">See photos & recordings</NuxtLink>
              <NuxtLink class="btn ghost" to="/events">Upcoming events</NuxtLink>
            </template>
          </div>
          <div class="evp-share">
            <span>Know someone who'd love this?</span>
            <button type="button" @click="share">
              <EventIcon :name="shared ? 'check' : 'share'" :size="16" /> {{ shared ? 'Link copied' : 'Share event' }}
            </button>
          </div>
          <NuxtLink class="evp-back" to="/events">← All events</NuxtLink>
        </aside>
      </div>
    </section>

    <section v-else class="evp">
      <div class="container evp-missing">
        <p>We couldn't find that event — it may have been moved or removed.</p>
        <NuxtLink class="btn" to="/events">See all events</NuxtLink>
      </div>
    </section>

    <!-- what else is coming up -->
    <section v-if="event && more.length" class="evp-more">
      <div class="container">
        <div class="section-head">
          <p class="eyebrow">Don't miss</p>
          <h2>More upcoming events</h2>
        </div>
        <div class="evp-more-grid">
          <EventCard v-for="e in more" :key="e.id" v-bind="e" :to="eventPath(e)" />
        </div>
      </div>
    </section>

    <SiteFooter />
  </div>
</template>

<style scoped>
/* hero: quick facts + actions */
.evp-chips { list-style: none; padding: 0; margin: 1.1rem 0 0; display: flex; flex-wrap: wrap; gap: .55rem; }
.evp-chips li { display: inline-flex; align-items: center; gap: .45rem; padding: .4rem .9rem; border-radius: 999px; font-size: .95rem; font-weight: 600;
  background: rgba(255, 255, 255, .12); border: 1px solid rgba(255, 255, 255, .22); color: #fff; backdrop-filter: blur(4px); }
.evp-hero-actions { display: flex; flex-wrap: wrap; gap: .7rem; margin-top: 1.5rem; }
.evp-hero-actions .btn { padding: .75rem 1.6rem; font-size: 1rem; }
.evp-ghost { display: inline-flex; align-items: center; gap: .5rem; padding: .7rem 1.3rem; border-radius: 999px; font: inherit; font-size: 1rem; font-weight: 700;
  color: #fff; background: transparent; border: 1.5px solid rgba(255, 255, 255, .5); cursor: pointer; transition: background .2s, border-color .2s; }
.evp-ghost:hover { background: rgba(255, 255, 255, .12); border-color: #fff; }

.evp { padding: 3.5rem 0 4.5rem; }
.evp-layout { display: grid; grid-template-columns: minmax(0, 1fr) 400px; gap: 2.5rem; align-items: start; }
@media (max-width: 980px) { .evp-layout { grid-template-columns: 1fr; } }
.evp-main { display: grid; gap: 2rem; min-width: 0; }

/* poster */
.evp-poster { position: relative; aspect-ratio: 16 / 9; border-radius: 24px; overflow: hidden; background: var(--color-secondary);
  box-shadow: 0 18px 40px rgba(20, 24, 29, .12); }
.evp-poster img { width: 100%; height: 100%; object-fit: cover; }
.evp-poster.is-plain { background:
  radial-gradient(circle at 1px 1px, rgba(255, 255, 255, .14) 1px, transparent 0) 0 0 / 20px 20px,
  linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 35%, var(--color-secondary)) 70%, var(--color-secondary)); }
.evp-poster-plain { position: absolute; inset: 0; display: grid; place-content: center; justify-items: center; gap: .8rem; color: #fff; }
.evp-poster-plain span { font-family: var(--font-heading); font-size: 1.6rem; }
.evp-badge { position: absolute; top: 1.2rem; left: 1.2rem; display: grid; justify-items: center; gap: .25rem; min-width: 72px; padding: .7rem .8rem .6rem;
  border-radius: 16px; background: #fff; line-height: 1; box-shadow: 0 10px 24px rgba(20, 24, 29, .2); }
.evp-badge b { font-family: var(--font-heading); font-size: 2rem; color: var(--color-primary); }
.evp-badge span { font-size: .78rem; font-weight: 800; letter-spacing: .14em; color: var(--color-secondary); }

/* sections */
.evp-kicker { font-size: .8rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--color-primary); margin-bottom: .8rem; }
.evp-text { font-size: 1.1rem; line-height: 1.75; color: #4b5560; white-space: pre-line; }

.evp-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin: 0; }
@media (max-width: 560px) { .evp-facts { grid-template-columns: 1fr; } }
.evp-fact { background: #fff; border-radius: 18px; padding: 1.1rem 1.2rem; box-shadow: 0 10px 24px rgba(20, 24, 29, .06); display: grid; gap: .25rem; align-content: start; }
.evp-fact dt { display: flex; align-items: center; gap: .65rem; font-size: .78rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase;
  color: var(--color-secondary); margin-bottom: .35rem; }
.evp-icon { width: 38px; height: 38px; flex: none; display: grid; place-items: center; border-radius: 12px; background: var(--primary-soft); color: var(--color-primary); }
.evp-fact dd { margin: 0; color: #4b5560; font-size: 1rem; line-height: 1.5; }
.evp-sub { font-weight: 700; color: var(--color-primary) !important; font-size: .92rem !important; }
.evp-multiline { white-space: pre-line; }
.evp-link { display: inline-flex; align-items: center; gap: .3rem; margin-top: .35rem; font-size: .9rem; font-weight: 700; color: var(--color-primary); }
.evp-link:hover { gap: .5rem; }

.evp-people { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: .9rem; }
.evp-people li { display: flex; align-items: center; gap: .85rem; background: #fff; border-radius: 18px; padding: .85rem 1rem; box-shadow: 0 10px 24px rgba(20, 24, 29, .06); }
.evp-avatar { width: 48px; height: 48px; flex: none; display: grid; place-items: center; border-radius: 50%; font-family: var(--font-heading); font-size: 1.1rem;
  color: #fff; background: var(--color-primary); }
.evp-avatar.guest { background: var(--color-secondary); }
.evp-people b { display: block; color: var(--color-secondary); font-size: 1rem; line-height: 1.3; }
.evp-people small { display: inline-flex; align-items: center; gap: .3rem; font-size: .82rem; font-weight: 700; color: var(--color-primary); }

.evp-tags { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; gap: .5rem; }
.evp-tags li { display: inline-flex; align-items: center; gap: .35rem; background: var(--primary-soft); color: var(--color-primary); border-radius: 999px;
  padding: .32rem .85rem; font-size: .85rem; font-weight: 700; }

/* booking */
.evp-aside { position: sticky; top: 110px; display: grid; gap: 1rem; scroll-margin-top: 120px; }
#reserve { scroll-margin-top: 120px; }
@media (max-width: 980px) { .evp-aside { position: static; } }
.evp-card { background: #fff; border-radius: 22px; padding: 2rem 1.8rem; box-shadow: 0 18px 40px rgba(20, 24, 29, .1); display: grid; gap: .8rem; }
.evp-card h3 { font-size: 1.35rem; }
.evp-card .btn { width: 100%; text-align: center; }
.evp-note { color: #55606b; }
.evp-share { display: flex; align-items: center; justify-content: space-between; gap: .8rem; flex-wrap: wrap; background: #fff; border-radius: 18px;
  padding: .9rem 1.1rem; box-shadow: 0 10px 24px rgba(20, 24, 29, .06); font-size: .95rem; color: #55606b; }
.evp-share button { display: inline-flex; align-items: center; gap: .4rem; padding: .5rem 1rem; border-radius: 999px; border: 1.5px solid var(--color-primary);
  background: transparent; color: var(--color-primary); font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; }
.evp-share button:hover { background: var(--primary-soft); }
.evp-back { font-weight: 700; color: var(--color-secondary); justify-self: start; }
.evp-back:hover { color: var(--color-primary); }

.evp-more { padding: 0 0 5rem; }
.evp-more-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.6rem; margin-top: 1.8rem; }
@media (max-width: 980px) { .evp-more-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) { .evp-more-grid { grid-template-columns: 1fr; } }

.evp-missing { display: grid; gap: 1rem; justify-items: center; text-align: center; color: #55606b; }
</style>
