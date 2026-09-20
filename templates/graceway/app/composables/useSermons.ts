// Sermon + Series content model — mirrors the CMS content type field-for-field,
// so swapping this static seed for API data is a drop-in change.

export interface SermonSeries {
  slug: string
  name: string
  description: string
  cover: string
}

export interface SermonAttachment {
  name: string
  size: string
  url: string
}

export interface Sermon {
  slug: string
  title: string
  speaker: string
  date: string // date_preached, ISO
  seriesSlug?: string
  scripture?: string
  summary: string
  thumbnail?: string
  /** YouTube video id — rendered as a lazy embed, never self-hosted */
  videoId?: string
  /** uploaded audio served by the platform (sample file in the prototype) */
  audioUrl?: string
  /** rich text notes / transcript (paragraphs) */
  body?: string[]
  attachments?: SermonAttachment[]
  featured?: boolean
}

/** @olux-collection Sermon Series */
const series: SermonSeries[] = [
  {
    slug: 'rooted', name: 'Rooted',
    description: 'A four-week journey into what it takes to grow a faith that holds — planted, watered, pruned and fruitful.',
    cover: '/assets/images/gallery-6.jpg',
  },
  {
    slug: 'grace-in-the-psalms', name: 'Grace in the Psalms',
    description: 'Honest songs for honest people — finding every human emotion, and God in all of them, in the Psalms.',
    cover: '/assets/images/gallery-10.jpg',
  },
]

const AUDIO_SAMPLE = 'https://www.w3schools.com/html/horse.mp3'

/** @olux-collection Sermons */
const sermons: Sermon[] = [
  { // all three media
    slug: 'fruit-that-lasts', title: 'Fruit That Lasts', speaker: 'Rev. Daniel Okafor', date: '2026-09-13',
    seriesSlug: 'rooted', scripture: 'John 15:1–17', featured: true,
    summary: 'Fruitfulness is not produced, it is grown — by staying connected to the vine.',
    thumbnail: '/assets/images/event-2.jpg', videoId: 'dQw4w9WgXcQ', audioUrl: AUDIO_SAMPLE,
    body: [
      'Roots grow in the dark, unseen — and so does character. Jesus\' last extended metaphor before the cross is a vineyard, and in it he gives us the whole architecture of the Christian life: a vine, branches, a gardener, and fruit.',
      'Notice what the branch is never asked to do: strain. The branch has one job — remain. Every verb of effort in this passage belongs to the gardener. Our culture tells us to produce; Jesus invites us to abide, and promises the producing will follow.',
      'So the question this week is not "what are you achieving?" but "where are you attached?" Fruit that lasts grows from connection that lasts.',
    ],
    attachments: [
      { name: 'Printable notes (PDF)', size: '240 KB', url: '#' },
      { name: 'Small group questions (PDF)', size: '180 KB', url: '#' },
    ],
  },
  { // video only
    slug: 'dry-seasons', title: 'Dry Seasons', speaker: 'Rev. Daniel Okafor', date: '2026-09-06',
    seriesSlug: 'rooted', scripture: 'Psalm 1:1–6; Jeremiah 17:7–8',
    summary: 'The tree survives drought by where it is planted, not how it feels.',
    thumbnail: '/assets/images/gallery-9.jpg', videoId: 'dQw4w9WgXcQ',
  },
  { // audio only
    slug: 'planted-in-community', title: 'Planted in Community', speaker: 'Grace Lindqvist', date: '2026-08-30',
    seriesSlug: 'rooted', scripture: 'Acts 2:42–47',
    summary: 'Nobody grows alone — the first church devoted themselves to four things, all of them together.',
    thumbnail: '/assets/images/welcome.jpg', audioUrl: AUDIO_SAMPLE,
  },
  { // text only
    slug: 'good-soil', title: 'Good Soil', speaker: 'Samuel Reyes', date: '2026-08-23',
    seriesSlug: 'rooted', scripture: 'Mark 4:1–20',
    summary: 'The sower\'s seed never changes — the soil does. A written reflection on receptive hearts.',
    thumbnail: '/assets/images/gallery-6.jpg',
    body: [
      'Four soils, one seed. The parable of the sower is really a parable of soils — the seed is constant, generous, almost recklessly scattered. What varies is the ground it lands on.',
      'The path is the hardened heart: truth bounces. The rocks are the shallow heart: enthusiasm without depth. The thorns are the crowded heart — and Jesus names the thorns precisely: worries, wealth, and wanting. Not evil things. Just choking things.',
      'Good soil, it turns out, is not perfect soil. It is broken-up, weeded, attended-to soil. Which is hopeful news: soil can be worked.',
    ],
    attachments: [{ name: 'Reading plan — Mark (PDF)', size: '95 KB', url: '#' }],
  },
  { // video + text
    slug: 'the-lord-is-my-shepherd', title: 'The Lord Is My Shepherd', speaker: 'Rev. Daniel Okafor', date: '2026-08-16',
    seriesSlug: 'grace-in-the-psalms', scripture: 'Psalm 23',
    summary: 'The world\'s most familiar psalm, read slowly enough to be unfamiliar again.',
    thumbnail: '/assets/images/gallery-10.jpg', videoId: 'dQw4w9WgXcQ',
    body: [
      'We know this psalm so well we no longer hear it. Read slowly, it is startling: the LORD — the maker of galaxies — is *my* shepherd. Not humanity\'s in general. Mine.',
      'And the psalm\'s geography is honest: green pastures, yes, but also the darkest valley. The shepherd does not route around the valley. He walks through it, ahead of the sheep.',
    ],
  },
  { // audio + text
    slug: 'songs-in-the-night', title: 'Songs in the Night', speaker: 'Esther Mwangi', date: '2026-08-09',
    seriesSlug: 'grace-in-the-psalms', scripture: 'Psalm 42',
    summary: 'What to do when your soul is downcast and God feels far — the psalmist\'s surprising answer: keep singing.',
    thumbnail: '/assets/images/circle-2.jpg', audioUrl: AUDIO_SAMPLE,
    body: [
      'Psalm 42 gives us permission we didn\'t know we needed: to interrogate our own souls. "Why, my soul, are you downcast?" The psalmist talks to himself instead of merely listening to himself.',
      'And then the strangest line: "at night his song is with me." Faith sometimes means singing from memory in the dark — trusting at midnight what you knew at noon.',
    ],
  },
  { // video + audio
    slug: 'the-god-who-lifts', title: 'The God Who Lifts', speaker: 'Grace Lindqvist', date: '2026-08-02',
    seriesSlug: 'grace-in-the-psalms', scripture: 'Psalm 3; Psalm 121',
    summary: 'A worship-led message on the God who is a shield around us, our glory, and the lifter of our heads.',
    thumbnail: '/assets/images/event-3.jpg', videoId: 'dQw4w9WgXcQ', audioUrl: AUDIO_SAMPLE,
  },
  { // standalone, video only
    slug: 'one-fold-one-shepherd', title: 'One Fold, One Shepherd', speaker: 'Rev. Daniel Okafor', date: '2026-07-26',
    scripture: 'John 10:11–18',
    summary: 'Our church motto, John 10:16 — what it means to belong to the Shepherd\'s one flock in a divided city.',
    thumbnail: '/assets/images/circle-1.jpg', videoId: 'dQw4w9WgXcQ',
  },
]

// CMS-first: "Sermons" / "Sermon Series" collections feed these; the
// authored rows are the seed + offline fallback.
export const useSermonSeries = (): SermonSeries[] => {
  const rows = (useCms().items('sermon-series', []) as any[]).filter(s => s.slug && s.name)
  return rows.length ? rows as SermonSeries[] : series
}
export const useSermons = (): Sermon[] => {
  const rows = (useCms().items('sermons', []) as any[]).filter(s => s.slug && s.title)
  return rows.length ? rows.map(s => ({ ...sermons.find(a => a.slug === s.slug), ...s }) as Sermon) : sermons
}

export const sermonSeriesOf = (s: Sermon) => useSermonSeries().find(x => x.slug === s.seriesSlug)
export const sermonThumb = (s: Sermon) =>
  s.thumbnail || sermonSeriesOf(s)?.cover || '/assets/images/welcome.jpg'
export const sermonMedia = (s: Sermon): ('video' | 'audio' | 'text')[] => [
  ...(s.videoId ? ['video' as const] : []),
  ...(s.audioUrl ? ['audio' as const] : []),
  ...(s.body?.length ? ['text' as const] : []),
]
export const mediaIcon = { video: '▶', audio: '🎧', text: '✍' } as const
