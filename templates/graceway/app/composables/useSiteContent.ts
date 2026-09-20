// Single authored data source for content across the site.
// Components read their copy/images from here instead of hard-coding them,
// so a site built from this template edits one file (and CMS field edits
// can override at runtime, same pattern as useHeroCopy).
//
// Globals — usable from any page/component:
//   profile   church identity & contact details
//   services  service days & times (single source for every time mention)
//   events    every event with its full data (grids, carousels, reservations)
export type LinkItem = { label: string; to: string }
export type Photo = { src: string; alt: string; tall?: boolean }

export type Profile = {
  name: string
  shortName: string
  logo: { lead: string; bold: string }
  email: string
  phone: string
  phoneHref: string
  address: string
  city: string
  postalCode: string
  country: string
  /** one entry per office-hours window, e.g. weekdays vs Saturday */
  officeHours: { days: string; hours: string }[]
  copyright: string
}

export type Service = {
  label: string
  day: string
  time: string
}

export type ChurchEvent = {
  id: string
  img: string
  title: string
  text: string
  tags: string[]
  /** ISO start datetime — powers the countdown chip */
  date: string
  /** price per seat — 0 means a free RSVP */
  price: number
  seatsLeft: number
  /** primed/featured — the first flagged event renders as the big featured card */
  featured?: boolean
}

export type SocialMedia = {
  key: string
  name: string
  href: string
  /** brand color for pills/icons */
  color: string
  /** brand glyph — SVG path data (24×24 viewBox) */
  icon: string
  /** is the church present/broadcasting on this platform? false → grayed out */
  available: boolean
  /** optional pill tag, e.g. 'LIVE' | 'SOON' */
  tag?: string
}

export type HeroPill = {
  key: string
  label: string
  desc: string
  section: string
  img: string
  tint: string
}

export type HeroContent = {
  title: string
  /** the highlighted span inside the title */
  titleHighlight: string
  lead: string
  liveNote: string
  actions: LinkItem[]
  pills: HeroPill[]
}

export type WelcomeContent = {
  eyebrow: string
  title: string
  quote: string
  text: string
  cta: LinkItem
  img: Photo
}

export type SectionHead = {
  eyebrow: string
  title: string
  text: string
}

export type BibleStudyContent = {
  title: string
  sub: string
  cta: LinkItem
  note: string
  img: Photo
  chips: { icon: string; label: string; value: string }[]
}

export type YouthContent = {
  eyebrow: string
  title: string
  sub: string
  cta: LinkItem
  tiles: { left: string[]; right: string[] }
}

export type BooksContent = {
  title: string
  /** the highlighted span inside the title */
  titleHighlight: string
  sub: string
  cta: LinkItem
  img: Photo
}

export type BroadcastContent = {
  eyebrow: string
  title: string
  sub: string
  stats: { value: string; label: string }[]
}

export type LegalDoc = {
  title: string
  updated: string
  intro: string
  sections: { heading: string; body: string }[]
}

export type DonateContent = {
  title: string
  text: string
  cta: LinkItem
}

export type IconFact = { icon: string; label: string; value: string }

export type AboutContent = {
  chip: string
  title: string
  intro: string
  story: { title: string; text: string }[]
  facts: IconFact[]
  pillars: { chip: string; title: string; text: string }[]
  sideMission: { title: string; text: string }
  sideMinistries: { icon: string; title: string; meets: string }[]
  photos: { img: string; title: string }[]
}

export type WorshipContent = {
  title: string
  sub: string
  trendLabel: string
  trendLinks: LinkItem[]
  cta: LinkItem
  grid: {
    tileTop: { text: string; badge: string }
    tileSmall: { text: string; badge: string }
    photos: { col1: Photo; col2: Photo[]; col3: Photo[] }
  }
}

export type MinistryCard = {
  size: string
  color: string
  tag: string
  icon: string
  title: string
  text: string
  members: string
  meets: string
}

export type MinistryFeature = {
  to: string
  img: string
  tag: string
  title: string
  text: string
  facts: { label: string; value: string }[]
}

export type ContactMapContent = {
  chip: string
  title: string
  mapSrc: string
  mapTitle: string
}

export type ContactContent = {
  infoTitle: string
  intro: string
  followLabel: string
  formChip: string
  formTitle: string
  formSub: string
  topicPlaceholder: string
  topics: string[]
  thanks: string
  sendAnother: string
}

export type GivingContent = {
  chip: string
  title: string
  sub: string
  presets: number[]
  thanksTitle: string
  thanksText: string
  fine: string
  waysTitle: string
  ways: { icon: string; title: string; text: string }[]
  verse: { text: string; cite: string }
}

export type MinistryDetailEntry = {
  img: string
  alt: string
  text: string
  meets: string
  leader: string
}

export type JoinContent = {
  stepsEyebrow: string
  stepsTitle: string
  steps: { n: string; title: string; text: string }[]
  perksTitle: string
  perks: string[]
  note: { title: string; text: string }
  chip: string
  title: string
  sub: string
  interestPlaceholder: string
  interests: string[]
  fine: string
  thanks: string
  faqEyebrow: string
  faqTitle: string
  faqs: { q: string; a: string }[]
}

export type PrayerContent = {
  intro: { chip: string; title: string; paragraphs: string[]; facts: { label: string; value: string }[]; img: Photo }
  verse: { label: string; text: string; cite: string }
  rhythmsEyebrow: string
  rhythmsTitle: string
  rhythms: { icon: string; title: string; when: string; where: string; text: string }[]
  form: { chip: string; title: string; sub: string; thanks: string }
}

export type StoreBook = {
  slug: string
  name: string
  description: string
  category: string
  price_cents: number
  currency: string
  image: string
  /** 0–5 star rating shown on the card */
  rating?: number
  reviews?: number
  isNew?: boolean
  bestSeller?: boolean
  onDiscount?: boolean
}

export type BookStoreContent = {
  eyebrow: string
  title: string
  categoryTitle: string
  allLabel: string
  quickFilters: { key: 'new' | 'best' | 'discount'; icon: string; label: string }[]
  recTitle: string
  cta: { title: string; text: string; placeholder: string; button: string; thanks: string }
  books: StoreBook[]
}

export type WorshipShowcaseContent = {
  banner: { title: string; actions: LinkItem[]; img: Photo }
  trendingTitle: string
  libraryTitle: string
}

/** per-platform extras layered over the global `socials` entry with the same key */
export type BroadcastChannelExtra = {
  key: string
  handle: string
  followers: string
  live: boolean
  color: string
  watch: string
  blurb: string
}

export type BroadcastChannelsContent = {
  eyebrow: string
  title: string
  sub: string
  channels: BroadcastChannelExtra[]
  join: { title: string; text: string; cta: LinkItem }
}

export type WorshipSidebarContent = {
  songsLabel: string
  ccli: string
  historyLabel: string
  history: { img: string; text: string; sub: string; when: string }[]
  timesLabel: string
  times: { label: string; value: string }[]
  serveLabel: string
  serveNote: string
  serveCta: LinkItem
}

export type YouthEventsContent = {
  eyebrow: string
  title: string
  countdownLabel: string
  events: { id: string; day: string; month: string; date: string; title: string; text: string; spots: number }[]
}

export type YouthHighlightsContent = {
  eyebrow: string
  title: string
  sub: string
  clips: { poster: string; title: string; views: string }[]
}

export type CommunityCareContent = {
  helpLead: string
  helpPre: string
  helpPost: string
  programmesEyebrow: string
  programmesTitle: string
  impact: { value: string; label: string }[]
  story: { img: string; alt: string; quote: string; cite: string }
  involveEyebrow: string
  involveTitle: string
  involve: { icon: string; title: string; text: string; cta: LinkItem }[]
}

export type SermonsArchiveContent = {
  live: { title: string; text: string; cta: LinkItem }
  takeAway: { title: string; text: string; links: LinkItem[] }
}

export type SiteContent = {
  profile: Profile
  services: Service[]
  events: ChurchEvent[]
  socials: SocialMedia[]
  hero: HeroContent
  welcome: WelcomeContent
  worship: WorshipContent
  bibleStudy: BibleStudyContent
  youth: YouthContent
  books: BooksContent
  sermonsHead: SectionHead & { cta: LinkItem }
  eventsHead: SectionHead
  broadcast: BroadcastContent
  pastorsHead: SectionHead & { cta: LinkItem }
  donate: DonateContent
  /** legal pages keyed by route (/privacy-policy, /terms, /cookie-policy) */
  legal: Record<string, LegalDoc>
  about: AboutContent
  ministriesBento: MinistryCard[]
  ministriesOverview: MinistryFeature[]
  contactMap: ContactMapContent
  contact: ContactContent
  giving: GivingContent
  ministryDetail: Record<string, MinistryDetailEntry>
  join: JoinContent
  prayer: PrayerContent
  bookStore: BookStoreContent
  worshipShowcase: WorshipShowcaseContent
  broadcastChannels: BroadcastChannelsContent
  worshipSidebar: WorshipSidebarContent
  youthEvents: YouthEventsContent
  youthHighlights: YouthHighlightsContent
  communityCare: CommunityCareContent
  sermonsArchive: SermonsArchiveContent
}

/** split an authored `\n` string into lines for <br>-joined rendering */
export const contentLines = (text: string) => text.split('\n')

const profile: Profile = {
  name: 'Christ Apostolic Church, Blackburn',
  shortName: 'CAC Blackburn',
  logo: { lead: 'CAC', bold: 'Blackburn' },
  email: 'hello@cacblackburn.org',
  phone: '+1 705 55 50 000',
  phoneHref: 'tel:+17055550000',
  address: '85 Johnston Street',
  postalCode: 'BB2 1HY',
  city: 'Blackburn',
  country: 'United Kingdom',
  officeHours: [
    { days: 'Mon – Fri', hours: '9:00 AM – 4:00 PM' },
  ],
  copyright: '© 2026 Christ Apostolic Church, Blackburn. All rights reserved.',
}

/** @olux-collection Services */
const services: Service[] = [
  { label: 'Sunday Worship', day: 'Sunday', time: '10:00 AM' },
  { label: 'Wednesday Prayer', day: 'Wednesday', time: '7:00 PM' },
  { label: 'Bible Study', day: 'Friday', time: '9:00 PM' },
  { label: 'Youth Gathering', day: 'Friday', time: '6:30 PM' },
]

// social media platforms — `available: false` renders grayed out wherever shown
/** @olux-collection Socials */
const socials: SocialMedia[] = [
  { key: 'youtube', name: 'YouTube', href: 'https://youtube.com/@cacblackburn', color: '#ff0000', available: false, tag: '', icon: 'M23 7.2s-.2-1.6-.9-2.3c-.9-.9-1.9-.9-2.3-1C16.6 3.6 12 3.6 12 3.6s-4.6 0-7.8.3c-.4.1-1.4.1-2.3 1-.7.7-.9 2.3-.9 2.3S.8 9.1.8 11v1.8c0 1.9.2 3.8.2 3.8s.2 1.6.9 2.3c.9.9 2 .9 2.5 1 1.8.2 7.6.3 7.6.3s4.6 0 7.8-.4c.4-.1 1.4-.1 2.3-1 .7-.7.9-2.3.9-2.3s.2-1.9.2-3.8V11c0-1.9-.2-3.8-.2-3.8zM9.9 15.1V8.4l6.2 3.4-6.2 3.3z' },
  { key: 'facebook', name: 'Facebook', href: 'https://facebook.com/cacblackburn', color: '#1877f2', available: true, tag: '', icon: 'M13.5 22v-9h3l.5-3.5h-3.5V7.2c0-1 .3-1.7 1.8-1.7H17V2.2c-.3 0-1.4-.2-2.6-.2-2.6 0-4.4 1.6-4.4 4.5v3H7V13h3v9h3.5z' },
  { key: 'instagram', name: 'Instagram', href: 'https://instagram.com/cacblackburn', color: '#8a3ab9', available: false, icon: 'M12 2.2c3.2 0 3.6 0 4.8.1 1.2.1 1.9.2 2.5.5.6.2 1.1.5 1.6 1 .5.5.8 1 1 1.6.2.6.4 1.3.5 2.5.1 1.2.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 1.2-.2 1.9-.5 2.5-.2.6-.5 1.1-1 1.6-.5.5-1 .8-1.6 1-.6.2-1.3.4-2.5.5-1.2.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-1.2-.1-1.9-.2-2.5-.5-.6-.2-1.1-.5-1.6-1-.5-.5-.8-1-1-1.6-.2-.6-.4-1.3-.5-2.5-.1-1.2-.1-1.6-.1-4.8s0-3.6.1-4.8c.1-1.2.2-1.9.5-2.5.2-.6.5-1.1 1-1.6.5-.5 1-.8 1.6-1 .6-.2 1.3-.4 2.5-.5 1.2-.1 1.6-.1 4.8-.1zm0 2c-3.1 0-3.5 0-4.7.1-1.1.1-1.7.2-2.1.4-.5.2-.9.4-1.2.8-.4.4-.6.7-.8 1.2-.2.4-.3 1-.4 2.1-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1 1.1.2 1.7.4 2.1.2.5.4.9.8 1.2.4.4.7.6 1.2.8.4.2 1 .3 2.1.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c1.1-.1 1.7-.2 2.1-.4.5-.2.9-.4 1.2-.8.4-.4.6-.7.8-1.2.2-.4.3-1 .4-2.1.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-1.1-.2-1.7-.4-2.1-.2-.5-.4-.9-.8-1.2-.4-.4-.7-.6-1.2-.8-.4-.2-1-.3-2.1-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.4a5.4 5.4 0 1 1 0 10.8 5.4 5.4 0 0 1 0-10.8zm0 2a3.4 3.4 0 1 0 0 6.8 3.4 3.4 0 0 0 0-6.8zm5.6-3.5a1.3 1.3 0 1 1 0 2.6 1.3 1.3 0 0 1 0-2.6z' },
  { key: 'tiktok', name: 'TikTok', href: 'https://tiktok.com/@cacblackburn', color: '#14181d', available: false, tag: '', icon: 'M16.6 2h3.1c.2 1.8 1.2 3.5 2.8 4.4.5.3 1 .5 1.5.6v3.3c-1.6-.1-3.1-.6-4.4-1.5v6.8c0 1.5-.4 3-1.3 4.2a7.2 7.2 0 0 1-8.9 2.4 7.2 7.2 0 0 1-3.9-8 7.2 7.2 0 0 1 7.5-5.6v3.4a3.8 3.8 0 0 0-4.2 2.6 3.8 3.8 0 0 0 5.5 4.4c1-.6 1.6-1.7 1.6-2.9V2z' },
  { key: 'x', name: 'X (Twitter)', href: 'https://x.com/cacblackburn', color: '#14181d', available: false, icon: 'M18.2 2.3h3.3l-7.3 8.3L22.8 22h-6.7l-5.3-6.9L4.8 22H1.5l7.8-8.9L1.1 2.3H8l4.8 6.3 5.4-6.3zm-1.2 17.7h1.8L7 4.2H5l12 15.8z' },
  { key: 'rss', name: 'RSS', href: '/broadcast', color: '#f26522', available: false, icon: 'M4 4.4v3.2c6.8 0 12.4 5.6 12.4 12.4h3.2C19.6 11.4 12.6 4.4 4 4.4zm0 6.4v3.2a5.6 5.6 0 0 1 5.6 5.6h3.2c0-4.9-3.9-8.8-8.8-8.8zM6.2 15.6a2.2 2.2 0 1 0 0 4.4 2.2 2.2 0 0 0 0-4.4z' },
  { key: 'twitch', name: 'Twitch', href: 'https://twitch.tv/cacblackburn', color: '#9146ff', available: false, icon: 'M4.3 3 3 6.4v13.7h4.7V22h2.6l2.5-1.9h3.8L21 15.6V3H4.3zm15 11.7-2.9 2.9h-4.6l-2.5 1.9v-1.9H5.4V4.7h13.9v10zM16.6 7.7v5h-1.7v-5h1.7zm-4.6 0v5h-1.7v-5H12z' },
]

/** @olux-collection Events */
const events: ChurchEvent[] = [
  { id: 'picnic', date: '2026-09-21T12:00:00', img: '/assets/images/event-1.jpg', title: 'Community Picnic in Riverside Park', text: 'Bring a dish and a friend — games, music and food for the whole neighbourhood. Sep 21, 12:00 PM.', tags: ['Community', 'Family', 'Food'], price: 0, seatsLeft: 120, featured: true },
  { id: 'fooddrive', date: '2026-10-04T09:00:00', img: '/assets/images/event-2.jpg', title: 'Harvest Food Drive', text: 'Help us collect and sort donations for local families. Volunteers of all ages welcome. Oct 4, 9:00 AM.', tags: ['Outreach', 'Volunteer'], price: 0, seatsLeft: 40 },
  { id: 'worshipnight', date: '2026-10-18T19:00:00', img: '/assets/images/event-3.jpg', title: 'Worship Night', text: 'An evening of music, prayer and candlelight in the main sanctuary. Oct 18, 7:00 PM.', tags: ['Worship', 'Prayer', 'Music'], price: 10, seatsLeft: 85 },
  { id: 'newcomers', date: '2026-11-01T12:30:00', img: '/assets/images/circle-1.jpg', title: 'Newcomers\' Lunch', text: 'New to CAC Blackburn? Join the pastors for lunch and hear the story of our church. Nov 1, 12:30 PM.', tags: ['Welcome', 'Food'], price: 0, seatsLeft: 24 },
  { id: 'choir', date: '2026-09-22T19:00:00', img: '/assets/images/circle-2.jpg', title: 'Christmas Choir Rehearsals', text: 'All voices welcome as we prepare carols for the Christmas Eve service. Tuesdays, 7:00 PM.', tags: ['Music', 'Christmas'], price: 0, seatsLeft: 30 },
  { id: 'youthgames', date: '2026-11-14T18:30:00', img: '/assets/images/circle-3.jpg', title: 'Youth Games Night', text: 'Pizza, tournaments and big questions for teens in the Youth Hall. Nov 14, 6:30 PM.', tags: ['Youth', 'Games'], price: 5, seatsLeft: 46 },
]

const shortTime = (t: string) => t.replace(':00 ', ' ')
const asBool = (v: any) => typeof v === 'boolean' ? v : v === 'true' || v === '1' || v === 1

export const useSiteContent = (): SiteContent => {
  // ── CMS-first overlays: the Services/Socials/Site Profile collections
  // (seeded from the authored rows below) override at runtime; the authored
  // rows carry the pristine template and any CMS-unreachable render. ──
  const { items } = useCms()
  const cmsServices = (items('services', []) as any[]).filter(s => s.label && s.time)
  const mergedServices: Service[] = cmsServices.length ? cmsServices.map(s => ({ day: '', ...s })) : services
  const cmsSocials = (items('socials', []) as any[]).filter(s => s.key)
  const mergedSocials: SocialMedia[] = cmsSocials.length
    ? cmsSocials.map(s => ({ ...socials.find(a => a.key === s.key), ...s, available: asBool(s.available) } as SocialMedia))
    : socials
  const profileRow = (items('siteProfile', []) as any[])[0]
  const mergedProfile: Profile = profileRow
    ? {
        ...profile,
        ...Object.fromEntries(Object.entries(profileRow).filter(([k, v]) => k in profile && v !== '' && v != null)),
        logo: { lead: profileRow.logoLead || profile.logo.lead, bold: profileRow.logoBold || profile.logo.bold },
        officeHours: Array.isArray(profileRow.officeHours) ? profileRow.officeHours : profile.officeHours,
      }
    : profile

  // derived from the (merged) service globals so time mentions never drift apart
  const sundayServices = mergedServices.filter(s => s.day === 'Sunday')
  const sundayTimesShort = sundayServices.map(s => shortTime(s.time)).join(' & ')
  // long times, e.g. "9:00 & 11:00 AM"
  const sundayTimesLong = sundayServices.map(s => s.time.replace(' AM', '').replace(' PM', '')).join(' & ') + ' ' + (sundayServices[0]?.time.slice(-2) ?? 'AM')

  return {
  profile: mergedProfile,
  services: mergedServices,
  events,
  socials: mergedSocials,
  hero: {
    title: 'Connecting believers,',
    titleHighlight: 'strengthening faith',
    lead: "We're a church family building a community where everyone belongs — come as you are, grow in faith, and serve alongside people who care.",
    liveNote: `Live every Sunday — ${sundayTimesLong}, wherever you are.`,
    actions: [
      { label: '▶ Watch Live', to: '/broadcast' },
      { label: 'Join the Church', to: '/newsletter' },
    ],
    pills: [
      { key: 'store', label: 'Store', desc: 'Books, music and church merchandise — every purchase supports our outreach.', section: 'store', img: '/assets/images/circle-1.jpg', tint: 'tint-yellow' },
      { key: 'events', label: 'Events', desc: 'Picnics, food drives, worship nights — life together beyond Sunday.', section: 'events', img: '/assets/images/event-1.jpg', tint: 'tint-red' },
      { key: 'bible', label: 'Bible Study', desc: 'Midweek studies and small groups digging deeper into the Word.', section: 'bible-study', img: '/assets/images/circle-2.jpg', tint: 'tint-gold' },
      { key: 'sermons', label: 'Sermons & Blog', desc: 'Catch up on recent messages and read reflections from our pastors.', section: 'sermons', img: '/assets/images/event-2.jpg', tint: 'tint-blue' },
      { key: 'youth', label: 'Youth Ministry', desc: 'A place for teens to ask big questions and build real friendships.', section: 'youth', img: '/assets/images/circle-3.jpg', tint: 'tint-teal' },
      { key: 'worship', label: 'Worship', desc: `Join us Sundays at ${sundayTimesLong} for music, prayer and teaching.`, section: 'worship', img: '/assets/images/event-3.jpg', tint: 'tint-green' },
    ],
  },
  welcome: {
    eyebrow: 'Who we are',
    title: 'A church for the whole community',
    quote: '"Let all that you do be done in love." — 1 Corinthians 16:14',
    text: 'For over thirty years, CAC Blackburn has been a gathering place for families, students and neighbours. We believe faith grows best in community — through honest worship, shared meals, and serving the city we love.',
    cta: { label: 'Learn More About Us', to: '/about' },
    img: { src: '/assets/images/welcome.jpg', alt: 'Congregation gathered together in worship' },
  },
  worship: {
    title: 'Find your place\nin worship',
    sub: 'Two services every Sunday — heartfelt praise, honest teaching and prayer for every generation. Come as you are.',
    trendLabel: '📈 Join now',
    trendLinks: [
      ...sundayServices.map(s => ({ label: `${s.day} ${shortTime(s.time)}`, to: '#' })),
      { label: 'Choir', to: '#' },
      { label: '4+ More teams', to: '/ministries' },
    ],
    cta: { label: 'Explore Worship & Praise', to: '/worship' },
    grid: {
      tileTop: { text: '2026 Praise\nCollections', badge: '🎵' },
      tileSmall: { text: `Sundays\n${sundayTimesShort}`, badge: '✝' },
      photos: {
        col1: { src: '/assets/images/circle-1.jpg', alt: 'Worship leader at the pulpit' },
        col2: [
          { src: '/assets/images/event-2.jpg', alt: 'Choir singing' },
          { src: '/assets/images/circle-2.jpg', alt: 'Member in prayer', tall: true },
        ],
        col3: [
          { src: '/assets/images/event-3.jpg', alt: 'Congregation worshipping' },
          { src: '/assets/images/circle-3.jpg', alt: 'Young worshipper smiling' },
        ],
      },
    },
  },
  bibleStudy: {
    title: 'Go deeper\nin the Word',
    sub: 'We believe faith grows best in circles small enough to know your name — midweek studies and small groups across Blackburn.',
    cta: { label: 'Explore Bible Study', to: '/bible-study' },
    note: '✓ Free to join — every group welcomes first-timers',
    img: { src: '/assets/images/bible-study.jpg', alt: 'Group studying the Bible together' },
    chips: [
      { icon: '🌅', label: 'Morning Watch', value: 'Wed 6 AM' },
      { icon: '🏠', label: 'Home Groups', value: '12+ citywide' },
    ],
  },
  youth: {
    eyebrow: 'Youth ministry',
    title: 'A place for teens to belong',
    sub: "Ages 13–19 — Friday nights, honest Bible study and a worship team of their own. Big questions, real friendships, and a faith that's theirs.",
    cta: { label: 'Visit the Youth Page', to: '/youth' },
    tiles: {
      left: ['/assets/images/circle-1.jpg', '/assets/images/event-1.jpg', '/assets/images/pastor-2.jpg', '/assets/images/welcome.jpg'],
      right: ['/assets/images/event-3.jpg', '/assets/images/circle-2.jpg', '/assets/images/pastor-5.jpg'],
    },
  },
  books: {
    title: 'Read books that can change your',
    titleHighlight: 'life',
    sub: 'Devotionals, study guides and stories of faith — hand-picked by our pastors to help you grow between Sundays.',
    cta: { label: 'Browse the Store', to: '/contact' },
    img: { src: '/assets/images/bookstand.png', alt: 'Book stand with devotionals and study guides' },
  },
  sermonsHead: {
    eyebrow: 'Sermons & blog',
    title: 'Latest messages',
    text: 'Video, audio and written reflections from our preachers — catch up wherever you are.',
    cta: { label: 'View All Sermons', to: '/sermons' },
  },
  eventsHead: {
    eyebrow: 'Upcoming events',
    title: 'Life together, beyond Sunday',
    text: '',
  },
  broadcast: {
    eyebrow: 'Live broadcast',
    title: 'Worship with us,\nwherever you are',
    sub: "Can't make it in person? Every Sunday service is broadcast live on YouTube and Facebook — and soon on TikTok. Join thousands worshipping from home.",
    stats: [
      { value: '2', label: 'platforms live' },
      { value: '200+', label: 'sermons online' },
      { value: '52', label: 'live streams a year' },
    ],
  },
  pastorsHead: {
    eyebrow: 'Leadership',
    title: 'Meet our pastors',
    text: 'Shepherds, teachers and neighbours — here to walk with you.',
    cta: { label: 'View All Members', to: '/about#leadership' },
  },
  donate: {
    title: 'Give generously, change lives',
    text: 'Your giving keeps our doors open, our pantry stocked, and our outreach on the streets. Every gift, of any size, makes a difference in our city.',
    cta: { label: 'Give Online', to: '/donate' },
  },
  about: {
    chip: '✳ Our Story',
    title: 'A church for the whole community',
    intro: "For over thirty years, CAC Blackburn has been a gathering place for families, students and neighbours. Here is who we are, what we believe, and where we're headed.",
    story: [
      { title: 'How we began', text: 'CAC Blackburn started in 1992 as a handful of families meeting in a living room on Riverside Avenue. What began with shared meals and simple prayer has grown into a church family of over four hundred — but the heart is unchanged: honest worship, open doors, and a seat at the table for everyone.' },
      { title: 'What we believe', text: 'We believe faith grows best in community. We hold to the historic Christian faith — the Bible as our guide, grace as our foundation, and love as our practice. "Let all that you do be done in love" (1 Corinthians 16:14) is more than a verse on our wall; it shapes how we worship, serve and disagree well.' },
      { title: 'Where we’re going', text: 'Our vision is a church for the whole community — students, families and neighbours alike. Through food drives, home groups, youth programs and city partnerships, we want Blackburn to be measurably better because this church is here.' },
    ],
    facts: [
      { icon: '⛪', label: 'Founded', value: '1992 — over thirty years serving Blackburn' },
      { icon: '👥', label: 'Our Family', value: '400+ members across all generations' },
      { icon: '📍', label: 'Where We Gather', value: profile.address },
      { icon: '🕐', label: 'Sunday Services', value: `${sundayTimesLong} · Midweek Wed 7 PM` },
    ],
    pillars: [
      { chip: 'Mission Statement', title: 'Why we exist', text: 'To make Jesus known in Blackburn by loving God wholeheartedly, loving people unconditionally, and serving our city practically — one meal, one prayer, and one open door at a time.' },
      { chip: 'Our Vision', title: 'What we see ahead', text: 'A church family in every neighbourhood of Blackburn — where every generation worships together, every home has a group to belong to, and the city is measurably better because this church is here.' },
      { chip: 'Our Pledge', title: 'What you can count on', text: 'We pledge to keep our doors open to everyone, to handle every gift with integrity and transparency, to protect and nurture our children and youth, and to speak the truth in love — always.' },
    ],
    sideMission: { title: 'Our Mission', text: 'Loving God, loving people, serving the city' },
    sideMinistries: [
      { icon: '🙏', title: 'Sunday Worship', meets: 'Sundays' },
      { icon: '🧒', title: 'Kids & Youth', meets: 'Sun & Fri' },
      { icon: '🤝', title: 'Community Care', meets: 'Saturdays' },
      { icon: '📖', title: 'Bible Study', meets: 'Weeknights' },
      { icon: '🕯', title: 'Prayer Watch', meets: 'Wed 6 AM' },
      { icon: '🎵', title: 'Choir & Band', meets: 'Thursdays' },
    ],
    photos: [
      { img: '/assets/images/gallery-1.jpg', title: 'Sunday worship' },
      { img: '/assets/images/gallery-2.jpg', title: 'Community outreach' },
      { img: '/assets/images/gallery-3.jpg', title: 'Home groups' },
      { img: '/assets/images/gallery-4.jpg', title: 'Youth night' },
    ],
  },
  ministriesBento: [
    { size: 'small', color: 'm-red', tag: 'Worship', icon: '🙏', title: 'Sunday Worship', text: 'Gather every Sunday for heartfelt music, honest teaching and open arms.', members: '400+', meets: 'Sundays' },
    { size: 'small', color: 'm-green', tag: 'Next Gen', icon: '🧒', title: 'Kids & Youth', text: 'Safe, joyful programs where children and teens grow, question and belong.', members: '120+', meets: 'Sun & Fri' },
    { size: 'big', color: 'm-orange', tag: 'Outreach', icon: '🤝', title: 'Community Care', text: 'Food drives, shelter support and neighbourhood projects that serve our city — practical love, every single week.', members: '80+', meets: 'Saturdays' },
    { size: 'big', color: 'm-pink', tag: 'Discipleship', icon: '📖', title: 'Bible Study', text: 'Midweek small groups in homes across the city — study the Word, share a meal, and belong to a circle that knows your name.', members: '150+', meets: 'Weeknights' },
    { size: 'small', color: 'm-blue', tag: 'Prayer', icon: '🕯', title: 'Prayer Watch', text: 'Intercessors praying for the church, the city and every request received.', members: '40+', meets: 'Wed 6 AM' },
    { size: 'small', color: 'm-purple', tag: 'Music', icon: '🎵', title: 'Choir & Band', text: 'Choir, band and tech teams bringing every service to life — all levels welcome.', members: '60+', meets: 'Thursdays' },
  ],
  ministriesOverview: [
    {
      to: '/worship',
      img: '/assets/images/event-2.jpg',
      tag: 'Worship & Music',
      title: 'Worship that lifts the whole room',
      text: 'Choir, band and production teams lead the congregation every Sunday with heartfelt music and honest praise. Whether you sing, play an instrument or love the tech booth, there is a seat for you.',
      facts: [
        { label: 'Leader', value: 'Grace Lindqvist' },
        { label: 'Meets', value: 'Rehearsals — Thursdays 7:00 PM' },
        { label: 'Who', value: 'All ages & skill levels' },
      ],
    },
    {
      to: '/youth',
      img: '/assets/images/circle-3.jpg',
      tag: 'Youth Ministry',
      title: 'Big questions, real friendships',
      text: 'Teens gather for games, honest conversation and Bible study. A place to ask big questions, find mentors who listen, and build friendships that last well beyond Friday night.',
      facts: [
        { label: 'Leader', value: 'Samuel Reyes' },
        { label: 'Meets', value: 'Fridays 6:30 PM — Youth Hall' },
        { label: 'Who', value: 'Teens 13–19' },
      ],
    },
    {
      to: '/bible-study',
      img: '/assets/images/circle-2.jpg',
      tag: 'Bible Study',
      title: 'Around the Word, around the table',
      text: 'Midweek home groups across the city — share a meal, study scripture and do life together in a circle small enough to know your name. Twelve-plus groups meet citywide every week.',
      facts: [
        { label: 'Leaders', value: 'Ruth & Peter Alonso' },
        { label: 'Meets', value: 'Weeknights — homes citywide' },
        { label: 'Who', value: 'Beginner friendly' },
      ],
    },
    {
      to: '/prayer',
      img: '/assets/images/bible-study.jpg',
      tag: 'Prayer Watch',
      title: 'The engine room of the church',
      text: 'Intercessors who pray for the church, the city and every request left in our prayer box. Join the early morning watch in the chapel or pray with us from home.',
      facts: [
        { label: 'Leader', value: 'Esther Mwangi' },
        { label: 'Meets', value: 'Wednesdays 6:00 AM — Chapel' },
        { label: 'Who', value: 'Early birds welcome' },
      ],
    },
    {
      to: '/community-care',
      img: '/assets/images/event-1.jpg',
      tag: 'Community Care',
      title: 'Practical love for our city',
      text: 'From the neighbourhood food pantry to shelter support and mission trips abroad, we serve practical needs and share hope beyond our walls — every single week.',
      facts: [
        { label: 'Leader', value: 'Rev. Daniel Okafor' },
        { label: 'Meets', value: 'Food pantry — Saturdays 9:00 AM' },
        { label: 'Who', value: 'Open to all' },
      ],
    },
  ],
  contactMap: {
    chip: '✳ Our Location',
    title: 'Visit us for Sunday services and in-person meetings',
    mapSrc: 'https://www.openstreetmap.org/export/embed.html?bbox=-2.5205%2C53.7330%2C-2.4370%2C53.7770&layer=mapnik&marker=53.7550%2C-2.4790',
    mapTitle: `Map to ${profile.shortName}`,
  },
  contact: {
    infoTitle: 'Contact Information',
    intro: "Have a question, a prayer request, or want to plan a visit? We're always glad to hear from you — reach out any time and we'll respond as quickly as possible.",
    followLabel: 'Follow Us',
    formChip: '✳ Get In Touch',
    formTitle: 'Get In Touch',
    formSub: 'We would love to hear about your visit, your questions or how we can pray with you. Fill out the form and our team will get back to you soon.',
    topicPlaceholder: "What's this about?",
    topics: ['Planning a visit', 'Prayer request', 'Joining a ministry', 'Giving & donations', 'Something else'],
    thanks: '💛 Thank you — your message is on its way. We usually reply within a day or two.',
    sendAnother: 'Send Another',
  },
  giving: {
    chip: '💝 Give Online',
    title: 'Sow into the work',
    sub: 'Every gift stays with the ministry — the pantry, the youth hall, the Sunday welcome. Secure card payment, receipt by email.',
    presets: [10, 25, 50, 100],
    thanksTitle: 'Thank you for your generosity! 🎉',
    thanksText: 'Your gift has been received. A receipt is on its way to your inbox — and our team prays over every gift on Wednesday mornings.',
    fine: '🔒 Payments handled by Stripe. We never see your card details.',
    waysTitle: 'Other ways to give',
    ways: [
      { icon: '🏦', title: 'Bank transfer', text: `${profile.shortName} · Sort 20-45-45 · Account 1234 5678\nReference: your name or "Tithe".` },
      { icon: '🧺', title: 'In person', text: 'The offering basket at any Sunday service, or the giving box by the welcome desk.' },
      { icon: '📝', title: 'Gift Aid', text: 'UK taxpayer? A Gift Aid declaration adds 25p to every £1 at no cost to you — ask at the office.' },
    ],
    verse: {
      text: '"Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver."',
      cite: '— 2 Corinthians 9:7',
    },
  },
  ministryDetail: {
    '/kids': {
      img: '/assets/images/circle-3.jpg', alt: 'Kids ministry',
      text: 'A safe, joyful world from nursery to grade six — stories, songs and play while parents worship.',
      meets: 'Both Sunday services — Kids Wing', leader: 'Ruth Alonso',
    },
    '/prayer': {
      img: '/assets/images/circle-2.jpg', alt: 'Prayer ministry',
      text: 'Intercessors praying for the church, the city and every request received — join the Wednesday 6 AM watch or pray from home.',
      meets: 'Wednesdays 6:00 AM — The Chapel', leader: 'Esther Mwangi',
    },
  },
  join: {
    stepsEyebrow: 'Your journey',
    stepsTitle: 'Four simple steps to belonging',
    steps: [
      { n: '01', title: 'Plan a visit', text: `Come to any Sunday service — ${sundayTimesLong}. No dress code, no pressure; just come as you are and say hello at the welcome desk.` },
      { n: '02', title: 'Newcomers\' lunch', text: 'Once a month the pastors host lunch for new faces. Hear the story of the church, ask anything, and meet others who are new too.' },
      { n: '03', title: 'Membership class', text: 'A relaxed two-session class on what we believe, how the church is led, and what belonging here means. Runs every other month.' },
      { n: '04', title: 'Find your place', text: 'Join a home group and a ministry that fits your gifts — from worship and kids to community care. This is where church becomes family.' },
    ],
    perksTitle: 'What happens when you join?',
    perks: [
      'A personal welcome from our pastoral team',
      'Weekly newsletter with sermons, events and prayer points',
      'An invitation to the next newcomers\' lunch',
      'Help finding a small group or ministry that fits you',
    ],
    note: {
      title: 'No forms required to visit.',
      text: "This form just helps us welcome you well — you're free to simply show up on Sunday.",
    },
    chip: '✳ Join the Church',
    title: 'Become part of the family',
    sub: 'Tell us a little about yourself and our welcome team will take it from there — usually within a couple of days.',
    interestPlaceholder: "Ministry you're curious about",
    interests: ['Sunday Worship', 'Kids & Youth', 'Community Care', 'Bible Study', 'Prayer Watch', 'Choir & Band', 'Not sure yet'],
    fine: 'One email a week, never shared, unsubscribe anytime.',
    thanks: "🎉 Welcome to the family! Look out for a welcome email this week — and we'd love to see you on Sunday.",
    faqEyebrow: 'Good to know',
    faqTitle: 'Questions people ask',
    faqs: [
      { q: 'Do I have to be baptised to join?', a: 'No — everyone is welcome to belong and take part from day one. Baptism and formal membership are steps we\'ll walk with you when you\'re ready, never a condition for a seat at the table.' },
      { q: 'What should I expect on a first visit?', a: 'About 90 minutes of music, a message and a warm welcome. Kids have their own program during both services, parking is free, and nobody will single you out or ask you to stand up.' },
      { q: 'Is there anything for my children?', a: 'Yes — Kids Church runs during both Sunday services for nursery through grade 6, with trained and vetted leaders. Teens have their own Friday-night youth ministry.' },
      { q: 'How is the church funded?', a: 'Entirely by the voluntary giving of members and friends. Giving is never expected of guests, and our finances are reviewed and reported to the congregation annually.' },
    ],
  },
  prayer: {
    intro: {
      chip: '✳ Prayer Watch',
      title: 'The engine room of the church',
      paragraphs: [
        'Everything we do begins here. Our intercessors pray for the church, the city and every request received — by name, every week. Led by Esther Mwangi, the Prayer Watch gathers early, stays late, and believes God hears.',
        "You don't need special words or years of experience. If you can show up, you can pray with us — and if you can't show up, we'll pray for you.",
      ],
      facts: [
        { label: 'Led by', value: 'Esther Mwangi' },
        { label: 'Main watch', value: 'Wednesdays 6:00 AM — The Chapel' },
        { label: 'Open to', value: 'Everyone, at every stage of faith' },
      ],
      img: { src: '/assets/images/circle-2.jpg', alt: 'People praying together' },
    },
    verse: {
      label: '📖 Our anchor',
      text: '"Devote yourselves to prayer, being watchful and thankful."',
      cite: '— Colossians 4:2',
    },
    rhythmsEyebrow: 'When we pray',
    rhythmsTitle: 'Rhythms of prayer',
    rhythms: [
      { icon: '🌅', title: 'Morning Watch', when: 'Wednesdays 6:00 AM', where: 'The Chapel', text: 'Start the day in stillness — an hour of worship, scripture and intercession before the city wakes.' },
      { icon: '🙏', title: 'Pre-Service Prayer', when: 'Sundays 8:15 AM', where: 'Main Hall', text: 'We cover every service in prayer before the doors open. All welcome, no experience needed.' },
      { icon: '🕯', title: 'Night Vigil', when: 'First Friday · 10 PM', where: 'Main Hall', text: 'A monthly night of extended worship and prayer for the church, the city and the nations.' },
      { icon: '🏠', title: 'Pray From Home', when: 'Anytime', where: 'Weekly prayer list', text: 'Receive the weekly prayer list by email and stand with us from wherever you are.' },
    ],
    form: {
      chip: '✳ Prayer Request',
      title: 'How can we pray for you?',
      sub: 'Every request is read, prayed over by the team, and kept confidential. Leave your name off if you prefer — God knows it.',
      thanks: '🕯 Thank you — your request is with the prayer team. You are not alone in this.',
    },
  },
  bookStore: {
    eyebrow: 'From our shelves',
    title: 'Hand-picked by our pastors',
    categoryTitle: 'Category',
    allLabel: 'All Products',
    quickFilters: [
      { key: 'new', icon: '✨', label: 'New Arrival' },
      { key: 'best', icon: '🏆', label: 'Best Seller' },
      { key: 'discount', icon: '💸', label: 'On Discount' },
    ],
    recTitle: 'Explore our recommendations',
    cta: {
      title: 'Ready to Get\nOur New Stuff?',
      text: "Join the store list and we'll email you when new devotionals, study guides and kids' titles land on the shelves.",
      placeholder: 'Your Email',
      button: 'Send',
      thanks: "You're on the list — we'll be in touch when new titles arrive.",
    },
    books: [
      { slug: 'rooted-30-days', name: 'Rooted: 30 Days in John 15', category: 'Devotionals', price_cents: 999, currency: 'gbp', image: '/assets/images/gallery-2.jpg', rating: 4.8, reviews: 132, bestSeller: true,
        description: 'A month-long devotional walking through the vine and the branches — one short reading, one question and one prayer a day.' },
      { slug: 'whole-armour-study-guide', name: 'The Whole Armour: A Study Guide', category: 'Study Guides', price_cents: 749, currency: 'gbp', image: '/assets/images/gallery-4.jpg', rating: 4.6, reviews: 87, isNew: true,
        description: 'A six-week small-group companion to Ephesians 6 with discussion questions, leader notes and memory verses.' },
      { slug: 'morning-watch-daily-prayers', name: 'Morning Watch: Daily Prayers', category: 'Prayer', price_cents: 699, currency: 'gbp', image: '/assets/images/gallery-6.jpg', rating: 4.9, reviews: 210, bestSeller: true,
        description: 'A pocket collection of morning prayers for the home, the commute and the quiet hour — used by our Wednesday watch.' },
      { slug: 'faith-for-the-city', name: 'Faith for the City', category: 'Books', price_cents: 1199, currency: 'gbp', image: '/assets/images/gallery-8.jpg', rating: 4.7, reviews: 64, onDiscount: true,
        description: 'Stories of outreach from our own streets — the pantry, the shelter and the neighbours who became family.' },
      { slug: 'kids-of-the-king', name: 'Kids of the King: Family Devotional', category: 'Family', price_cents: 849, currency: 'gbp', image: '/assets/images/gallery-11.jpg', rating: 4.8, reviews: 145, isNew: true,
        description: 'Fifty-two playful family devotions — a story, a giggle and a big question for every week of the year.' },
    ],
  },
  worshipShowcase: {
    banner: {
      title: 'Discover, sing and share your worship',
      actions: [
        { label: '▶ Watch Live', to: '/broadcast' },
        { label: 'Join the Team', to: '/contact' },
      ],
      img: { src: '/assets/images/pastor-2.jpg', alt: 'Worship leader' },
    },
    trendingTitle: 'Trending worship',
    libraryTitle: 'Worship library',
  },
  broadcastChannels: {
    eyebrow: 'Live broadcast',
    title: 'Worship with us, wherever you are',
    sub: 'Every Sunday service streams live. Pick your platform — follow the profile, or jump straight into the stream.',
    channels: [
      { key: 'youtube', handle: '@cacblackburn', color: '#d1242f', followers: '12.4k subscribers', live: true,
        watch: 'https://youtube.com/@cacblackburn/live',
        blurb: 'Full Sunday services, sermon replays and worship nights in HD.' },
      { key: 'facebook', handle: 'CAC Blackburn', color: '#1d5fd1', followers: '8.9k followers', live: true,
        watch: 'https://facebook.com/cacblackburn/live',
        blurb: 'Live services with real-time chat, event updates and photo albums.' },
      { key: 'instagram', handle: '@cacblackburn', color: '#c13584', followers: '6.2k followers', live: false,
        watch: 'https://instagram.com/cacblackburn',
        blurb: 'Daily encouragement, behind-the-scenes moments and reels from Sunday.' },
      { key: 'tiktok', handle: '@cacblackburn', color: '#14181d', followers: 'Coming soon', live: false,
        watch: 'https://tiktok.com/@cacblackburn/live',
        blurb: 'Short worship clips and testimonies — launching this season.' },
    ],
    join: {
      title: 'Watched online long enough?',
      text: `You're always welcome in the room. Plan a visit, meet the family, and make ${profile.shortName} your church home.`,
      cta: { label: 'Join the Church', to: '/newsletter' },
    },
  },
  worshipSidebar: {
    songsLabel: "🎵 What we're singing",
    ccli: 'Reproduced under CCLI licence #000000.',
    historyLabel: '🕘 History',
    history: [
      { img: '/assets/images/event-2.jpg', text: 'Sunday recap uploaded', sub: 'By the media team', when: 'Just now' },
      { img: '/assets/images/gallery-5.jpg', text: 'New song added', sub: 'Firm Foundation', when: '1 hr ago' },
      { img: '/assets/images/gallery-9.jpg', text: 'Choir set recorded', sub: 'Live from 11 AM', when: '2 hrs ago' },
      { img: '/assets/images/event-3.jpg', text: 'Worship night announced', sub: 'First Friday', when: '5 hrs ago' },
    ],
    timesLabel: '🗓 When we gather',
    times: [
      { label: 'Sunday services', value: sundayTimesLong },
      { label: 'Worship night', value: 'First Friday, 7:00 PM' },
      { label: 'Team rehearsal', value: 'Thursdays, 7:00 PM' },
    ],
    serveLabel: '🎚 Serve with us',
    serveNote: 'Vocals, instruments, sound desk or media — all skill levels, we train you.',
    serveCta: { label: 'Join the Worship Team', to: '/contact' },
  },
  youthEvents: {
    eyebrow: "What's coming up",
    title: "Don't miss the next one",
    countdownLabel: '🔥 All-Night Lock-In starts in',
    events: [
      { id: 'lockin', day: '26', month: 'Sep', date: '2026-09-26T18:30:00', title: 'All-Night Lock-In', text: 'Games, films, pizza and a 2 AM worship moment. Bring a sleeping bag!', spots: 18 },
      { id: 'camp', day: '17', month: 'Oct', date: '2026-10-17T09:00:00', title: 'Autumn Youth Camp', text: 'A weekend away in the hills — campfires, big questions, no phones (mostly).', spots: 31 },
      { id: 'serve', day: '07', month: 'Nov', date: '2026-11-07T09:00:00', title: 'City Serve Day', text: 'Food bank shift in the morning, milkshakes after. Serve your city with your crew.', spots: 12 },
    ],
  },
  youthHighlights: {
    eyebrow: 'Highlights',
    title: 'Relive the best moments',
    sub: 'Short clips from recent nights — filmed by us, starring you.',
    clips: [
      { poster: '/assets/images/gallery-1.jpg', title: 'Lock-in chaos 😂', views: '1.2k' },
      { poster: '/assets/images/gallery-3.jpg', title: 'Worship night', views: '860' },
      { poster: '/assets/images/gallery-7.jpg', title: 'Camp recap', views: '2.1k' },
      { poster: '/assets/images/gallery-11.jpg', title: 'Serve day', views: '640' },
    ],
  },
  communityCare: {
    helpLead: 'Need help right now?',
    helpPre: 'Come to the church office any weekday morning, or call',
    helpPost: 'Food parcels, a warm room, and someone to talk to — no appointment, no judgement.',
    programmesEyebrow: 'What we do',
    programmesTitle: 'Our outreach programmes',
    impact: [
      { value: '4,800+', label: 'meals shared this year' },
      { value: '120', label: 'families helped monthly' },
      { value: '85', label: 'volunteers involved' },
      { value: '2', label: 'partner churches abroad' },
    ],
    story: {
      img: '/assets/images/circle-2.jpg',
      alt: 'A neighbour helped by the food pantry',
      quote: '"I came for a food parcel and stayed for the cup of tea. Six months later I\'m the one pouring it for someone else."',
      cite: '— Maria, food pantry volunteer',
    },
    involveEyebrow: 'Get involved',
    involveTitle: 'Three ways to help this week',
    involve: [
      { icon: '🙋', title: 'Volunteer', text: "An hour a week changes someone's whole week. Every programme has a role that fits you.", cta: { label: 'Sign Up', to: '/contact' } },
      { icon: '💝', title: 'Give', text: 'Fund the pantry shelves, the flasks and the school fees. Every gift stays with the work.', cta: { label: 'Donate', to: '/contact' } },
      { icon: '🙏', title: 'Pray', text: 'Join the Wednesday Morning Watch as we pray for our town by name, street by street.', cta: { label: 'Prayer Watch', to: '/prayer' } },
    ],
  },
  sermonsArchive: {
    live: {
      title: '🔴 Live every Sunday',
      text: `Join the ${sundayTimesLong} service live from anywhere.`,
      cta: { label: '▶ Watch Live', to: '/broadcast' },
    },
    takeAway: {
      title: 'Take it with you',
      text: 'Sermons land on the podcast every Monday.',
      links: [
        { label: '✉ Get the Newsletter', to: '/newsletter' },
        { label: '🎧 Podcast & Platforms', to: '/broadcast' },
      ],
    },
  },
  legal: {
    '/privacy-policy': {
      title: 'Privacy Policy',
      updated: 'Last updated: 19 September 2026',
      intro: `This Privacy Policy explains how ${profile.name} ("we", "us") collects, uses and protects your personal information when you visit our website or take part in church life.`,
      sections: [
        { heading: '1. Information we collect', body: 'We may collect your name, email address, phone number and any message you send us through our contact, prayer, giving or membership forms. We also collect basic, anonymous usage data (such as pages visited) to help us improve the site.' },
        { heading: '2. How we use your information', body: 'We use your information to respond to your enquiries, welcome you to the church, process donations, send newsletters you have signed up for, and keep appropriate records of church membership. We never sell your data.' },
        { heading: '3. Legal basis', body: 'We process personal data on the basis of your consent, our legitimate interest in running the church and its ministries, and, where applicable, legal obligations (for example Gift Aid records).' },
        { heading: '4. Sharing your information', body: 'Your data is only shared with trusted service providers that help us operate (such as our payment processor and email provider), and only as far as needed. We may disclose information where the law requires it.' },
        { heading: '5. Data retention', body: 'We keep personal information only as long as necessary for the purposes above, or as required by law, after which it is securely deleted.' },
        { heading: '6. Your rights', body: `You may request access to, correction of, or deletion of your personal data at any time, and you may withdraw consent to communications. Contact us at ${profile.email} to exercise any of these rights.` },
        { heading: '7. Contact', body: `Questions about this policy can be sent to ${profile.email} or by post to ${profile.address}, ${profile.city}.` },
      ],
    },
    '/terms': {
      title: 'Terms & Conditions of Use',
      updated: 'Last updated: 19 September 2026',
      intro: `These Terms govern your use of the ${profile.name} website. By using the site you agree to them.`,
      sections: [
        { heading: '1. Use of this website', body: 'The site is provided for personal, non-commercial use to learn about our church, services, events and ministries. You agree not to misuse the site, attempt to gain unauthorised access, or use it in any unlawful way.' },
        { heading: '2. Content and accuracy', body: 'We do our best to keep service times, events and other information accurate and up to date, but details can change. Content is provided "as is" without warranties of any kind.' },
        { heading: '3. Intellectual property', body: 'Unless stated otherwise, the content on this site (text, images, logos and media) belongs to the church or its licensors. You may share links to our pages, but please ask before reproducing content elsewhere.' },
        { heading: '4. Donations and payments', body: 'Online donations and event payments are processed securely by our payment provider. Gifts are voluntary and, except where required by law, non-refundable. Receipts are issued by email where an address is provided.' },
        { heading: '5. Third-party links', body: 'Our site may link to external websites (such as social media platforms). We are not responsible for the content or privacy practices of those sites.' },
        { heading: '6. Limitation of liability', body: 'To the fullest extent permitted by law, we are not liable for any loss or damage arising from your use of, or inability to use, this website.' },
        { heading: '7. Changes to these terms', body: 'We may update these Terms from time to time. Continued use of the site after changes are posted means you accept the updated Terms.' },
        { heading: '8. Contact', body: `Questions about these Terms can be sent to ${profile.email}.` },
      ],
    },
    '/cookie-policy': {
      title: 'Cookie Policy',
      updated: 'Last updated: 19 September 2026',
      intro: `This Cookie Policy explains how the ${profile.name} website uses cookies and similar technologies.`,
      sections: [
        { heading: '1. What are cookies?', body: 'Cookies are small text files stored on your device when you visit a website. They help the site work properly and remember your preferences between visits.' },
        { heading: '2. Cookies we use', body: 'Essential cookies: needed for the site to function (for example remembering an event reservation in progress). Preference cookies: remember choices you make, such as forms you have already submitted. We do not use advertising cookies.' },
        { heading: '3. Third-party cookies', body: 'Some embedded content — such as maps, videos or our payment provider checkout — may set their own cookies. These are controlled by those providers and subject to their own policies.' },
        { heading: '4. Managing cookies', body: 'You can control or delete cookies through your browser settings. Blocking essential cookies may stop parts of the site (such as forms and reservations) from working correctly.' },
        { heading: '5. Contact', body: `Questions about this policy can be sent to ${profile.email}.` },
      ],
    },
  },
}
}
