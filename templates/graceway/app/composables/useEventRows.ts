import type { ChurchEvent } from './useSiteContent'

const slugify = (s: string) => s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
  .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')

/** An event's own page. */
export const eventPath = (e: { id: string }) => `/events/${encodeURIComponent(e.id)}`

/** True once the event's start time has passed. */
export const eventIsPast = (e: { date?: string }) => !!e.date && new Date(e.date).getTime() < Date.now()

const soonestFirst = (a: ChurchEvent, b: ChurchEvent) => (a.date ?? '').localeCompare(b.date ?? '')

/** Events that haven't started yet, soonest first — optionally one ministry's (e.g. 'youth'). */
export const upcomingEvents = (rows: ChurchEvent[], ministry?: string) =>
  rows.filter(e => !eventIsPast(e) && (!ministry || e.ministry === ministry)).sort(soonestFirst)

/** Events whose date has passed, newest first — they live in the archive. */
export const pastEvents = (rows: ChurchEvent[]) => rows.filter(eventIsPast).sort((a, b) => soonestFirst(b, a))

/**
 * Seats this visitor has reserved, per event id — shared by the events grid
 * and the event page, and remembered in this browser.
 */
export const useEventReservations = () => {
  const reserved = useState<Record<string, number>>('event-reservations', () => ({}))
  const loaded = useState<boolean>('event-reservations-loaded', () => false)
  if (import.meta.client && !loaded.value) {
    try { reserved.value = JSON.parse(localStorage.getItem('event-reservations') || '{}') } catch { /* private mode */ }
    loaded.value = true
  }
  const record = (id: string, seats: number) => {
    reserved.value = { ...reserved.value, [id]: seats }
    try { localStorage.setItem('event-reservations', JSON.stringify(reserved.value)) } catch { /* private mode */ }
  }
  return { reserved, record }
}

/** featured is an on/off toggle in the CMS (older rows may still hold "yes"/"no") */
export const isFeatured = (v: unknown) => v === true || v === 'yes' || v === 'true'

/**
 * Every event, normalised for the events page, home block and archive:
 * CMS "Events" collection rows (authored rows only in a template preview).
 * Each event is identified by its slug — the CMS makes it from the title +
 * date (Y-M-D) and keeps it unique; an older row's own id, or one made the
 * same way here, stands in when it has none. Featured becomes a boolean and
 * an empty seatsLeft stays '' (= no seat limit).
 */
export const useEventRows = (): ChurchEvent[] => {
  const authored = useSiteContent().events
  const rows = (useCms().items('events', []) as any[]).filter(e => e.title)
  const list: any[] = (rows.length || useCms().isSite)
    ? rows.map(e => ({ ...authored.find(a => a.slug && a.slug === e.slug), ...e }))
    : authored
  const used = new Set<string>()
  return list.map((e) => {
    // the CMS entry's own id (rows from the site's collection) — blocks select events by it
    const cmsId = e._cid ? String(e.id ?? '') : ''
    let id = String(e.slug ?? '').trim() || String(e.id ?? '').trim() || slugify(`${e.title}-${String(e.date ?? '').slice(0, 10)}`) || 'event'
    for (let n = 2; used.has(id); n++) id = `${id.replace(/-\d+$/, '')}-${n}`
    used.add(id)
    const seats = e.seatsLeft === '' || e.seatsLeft === null || e.seatsLeft === undefined ? '' : Number(e.seatsLeft)
    return {
      ...e,
      id,
      slug: id,
      cmsId,
      featured: isFeatured(e.featured),
      price: Number(e.price || 0),
      seatsLeft: Number.isNaN(seats) ? '' : seats,
      media: Array.isArray(e.media) ? e.media : [],
      ministry: e.ministry || 'church',
      mediaLayout: e.mediaLayout === 'slideshow' ? 'slideshow' : 'gallery',
    } as ChurchEvent
  })
}

/** The event type as visitors read it. */
export const eventTypeLabel = (ministry?: string) =>
  ({ church: 'Church event', youth: 'Youth', men: "Men's ministry", women: "Women's ministry" } as Record<string, string>)[ministry || 'church'] ?? 'Church event'

/** "Free" or "£5" — what a seat costs. */
export const eventPriceLabel = (price?: number) => Number(price) > 0 ? `£${Number(price)}` : 'Free'

const eventDate = (iso?: string) => {
  const d = iso ? new Date(iso) : null
  return d && !Number.isNaN(d.getTime()) ? d : null
}
/** "Saturday, 14 November 2026" (the raw value when it isn't a date). */
export const eventDayLabel = (iso?: string, style: 'long' | 'short' = 'long') => {
  const d = eventDate(iso)
  if (!d) return iso ?? ''
  return style === 'long'
    ? d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    : d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })
}
/** "10:00 AM" ('' for a date without a time). */
export const eventTimeLabel = (iso?: string) => {
  const d = eventDate(iso)
  return d && /T\d|\s\d{1,2}:/.test(iso ?? '') ? d.toLocaleTimeString('en-GB', { hour: 'numeric', minute: '2-digit', hour12: true }).toUpperCase() : ''
}
/** Day / month / time pieces for a date badge (null without a valid date). */
export const eventBadge = (iso?: string) => {
  const d = eventDate(iso)
  return d ? { day: d.toLocaleDateString('en-GB', { day: 'numeric' }), month: d.toLocaleDateString('en-GB', { month: 'short' }).toUpperCase(), time: eventTimeLabel(iso) } : null
}
/** "8d 1h to go" until the event starts (null once it has). */
export const eventCountdown = (iso: string | undefined, now: number) => {
  const d = eventDate(iso)
  const diff = d ? d.getTime() - now : 0
  if (diff <= 0) return null
  const mins = Math.floor(diff / 60_000)
  const days = Math.floor(mins / 1440)
  const hours = Math.floor((mins % 1440) / 60)
  if (days > 0) return `${days}d ${hours}h to go`
  if (hours > 0) return `${hours}h ${mins % 60}m to go`
  return `${mins}m to go`
}
/** The first line of a (multi-line) location — the venue name for a card. */
export const eventPlace = (location?: string) => (location ?? '').split('\n').map(s => s.trim()).find(Boolean) ?? ''
