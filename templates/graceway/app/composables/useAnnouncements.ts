// Church announcements — news and notices. The About page shows the latest,
// /announcements lists them all and each has its own page (by slug).

export type Announcement = {
  title: string
  /** web address — the CMS makes it from the title + date, unique */
  slug: string
  /** when it was announced (ISO date & time) */
  date: string
  category: string
  /** one or two lines for cards and lists */
  summary: string
  /** the full announcement */
  body: string
  img: string
  /** pinned announcements lead every list */
  pinned: boolean
  /** an optional next step: a page or link, with its button text */
  link?: string
  linkLabel?: string
}

/** @olux-collection Announcements
 * @olux-field title text required
 * @olux-field slug slug from=title+date label="Slug (web address)"
 * @olux-field date datetime label="Date & time"
 * @olux-field category select options=General|Events|Ministries|Prayer|Giving|Notice
 * @olux-field summary textarea2 label="Summary (shown on cards)"
 * @olux-field body textarea10 label="Full announcement"
 * @olux-field img image label="Image (optional)"
 * @olux-field pinned toggle label="Pin to the top"
 * @olux-field link text label="Button link (optional, e.g. /events)"
 * @olux-field linkLabel text label="Button text"
 */
const announcements: Omit<Announcement, 'slug'>[] = [
  {
    title: 'New Sunday service times from November', date: '2026-10-04T12:00', category: 'Notice', pinned: true, img: '/assets/images/welcome.jpg',
    summary: 'From 1 November we move to two Sunday services — 9:00 AM and 11:00 AM — to make room for our growing church family.',
    body: 'From Sunday 1 November we are moving to two Sunday services, at 9:00 AM and 11:00 AM, to make room for our growing church family.\n\nBoth services follow the same pattern — worship, teaching and prayer — and Kids Church runs during both. The 11:00 AM service is livestreamed as usual.\n\nIf you would like to help with welcome, refreshments or the kids team at the new 9:00 AM service, speak to any of the pastors or get in touch through the contact page.',
    link: '/contact', linkLabel: 'Volunteer for 9 AM',
  },
  {
    title: 'Harvest food drive', date: '2026-10-01T09:00', category: 'Ministries', pinned: false, img: '/assets/images/gallery-9.jpg',
    summary: 'Bring tins, pasta and toiletries to any service in October — everything goes to Blackburn Foodbank.',
    body: 'Throughout October we are collecting food and toiletries for Blackburn Foodbank. Look for the harvest baskets in the foyer at every service.\n\nMost needed: tinned meat and fish, pasta sauce, long-life milk, tea and coffee, shower gel and nappies (sizes 4–6).\n\nThe Community Care team will deliver everything on Saturday 31 October — join them if you can.',
    link: '/community-care', linkLabel: 'About Community Care',
  },
  {
    title: 'Worship Night tickets now available', date: '2026-09-28T18:00', category: 'Events', pinned: false, img: '/assets/images/event-3.jpg',
    summary: 'An evening of music, prayer and candlelight on 18 October. Seats are limited — reserve yours online.',
    body: 'Our autumn Worship Night is on Sunday 18 October at 7:00 PM in the main sanctuary, with the Blackburn Gospel Choir joining us.\n\nSeats are limited, so please reserve yours online. Proceeds support the Media Team\'s new livestream equipment.',
    link: '/events', linkLabel: 'See upcoming events',
  },
  {
    title: 'Prayer Watch moves to the Chapel', date: '2026-09-20T07:00', category: 'Prayer', pinned: false, img: '',
    summary: 'The Wednesday 6 AM Prayer Watch now meets in the Chapel — coffee is on from 5:45.',
    body: 'The Wednesday 6:00 AM Prayer Watch has moved from the Church Hall to the Chapel, which is warmer and quieter in the early mornings.\n\nCoffee is on from 5:45 AM. You are welcome to come for ten minutes or the whole hour — and if you can\'t make it, you can always send a request through the prayer page.',
    link: '/prayer', linkLabel: 'Send a prayer request',
  },
]

const slugify = (s: string) => s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
  .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')

/**
 * Every announcement, pinned first, then newest first. CMS "Announcements"
 * rows on a site (authored rows only in a template preview); an entry with no
 * slug yet gets one from its title + date, the way the CMS makes it.
 */
export const useAnnouncements = (): Announcement[] => {
  const rows = (useCms().items('announcements', []) as any[]).filter(a => a.title)
  const list: any[] = (rows.length || useCms().isSite) ? rows : announcements
  const used = new Set<string>()
  return list.map((a) => {
    let slug = String(a.slug ?? '').trim() || slugify(`${a.title}-${String(a.date ?? '').slice(0, 10)}`) || 'announcement'
    for (let n = 2; used.has(slug); n++) slug = `${slug.replace(/-\d+$/, '')}-${n}`
    used.add(slug)
    return {
      ...a,
      slug,
      category: a.category || 'General',
      summary: a.summary || String(a.body ?? '').split('\n').find((l: string) => l.trim()) || '',
      body: a.body || a.summary || '',
      img: a.img || '',
      pinned: a.pinned === true || a.pinned === 'true' || a.pinned === 'yes' || a.pinned === 1,
    } as Announcement
  }).sort((x, y) => Number(y.pinned) - Number(x.pinned) || String(y.date).localeCompare(String(x.date)))
}

/** An announcement's own page. */
export const announcementPath = (a: { slug: string }) => `/announcements/${encodeURIComponent(a.slug)}`

/** "4 October 2026" */
export const announcementDate = (iso?: string) => {
  const d = iso ? new Date(iso) : null
  return d && !Number.isNaN(d.getTime()) ? d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : (iso ?? '')
}
