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

/** a media file attached to a sermon / study / song in the CMS */
export interface ContentMedia {
  type: 'video' | 'audio' | 'image'
  title?: string
  /** a file from Assets, or (video) a YouTube link */
  src: string
  /** poster shown before a video/recording plays */
  img?: string
}

export interface Sermon {
  slug: string
  title: string
  /** the author / preacher */
  speaker: string
  /** publish date & time, ISO — the sermon shows from then on */
  date: string
  seriesSlug?: string
  scripture?: string
  /** the description */
  summary: string
  thumbnail?: string
  /** YouTube video id — rendered as a lazy embed */
  videoId?: string
  /** an uploaded video file (from a media row) */
  videoFile?: string
  /** the video's poster image */
  poster?: string
  /** uploaded audio served by the platform */
  audioUrl?: string
  /** the content, as paragraphs (the CMS stores one text, blank-line separated) */
  body?: string[]
  media?: ContentMedia[]
  attachments?: SermonAttachment[]
  featured?: boolean
  published?: boolean
}

/** @olux-collection Sermon Series
 * @olux-field name text required label="Series name"
 * @olux-field slug text hidden
 * @olux-field description textarea6
 * @olux-field cover image label="Cover image"
 */
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

/** @olux-collection Sermons
 * @olux-field slug text hidden
 * @olux-field title textarea2 required
 * @olux-field speaker text label="Author"
 * @olux-field summary textarea6 label="Description"
 * @olux-field body textarea15 label="Content"
 * @olux-field date datetime label="Publish date & time"
 * @olux-field published toggle label="Published"
 * @olux-field thumbnail image label="Cover image"
 * @olux-field media rows label="Media"
 * @olux-field media.type select options=video|audio|image
 * @olux-field media.title text
 * @olux-field media.src media label="File from Assets (or a YouTube link)"
 * @olux-field media.img image label="Poster image" show=type:video|audio
 * @olux-field featured toggle
 * @olux-field seriesSlug select from=sermon-series:slug:name label="Series"
 * @olux-field scripture text label="Scripture (e.g. John 15:1–17)"
 * @olux-field attachments rows label="Downloads"
 * @olux-field attachments.name text label="Name"
 * @olux-field attachments.url media label="File (from Assets)"
 * @olux-field attachments.size text label="Size (optional, e.g. 240 KB)"
 */
// as the CMS stores them: the content is one text, paragraphs separated by a blank line
const sermons: (Omit<Sermon, 'body'> & { body?: string })[] = [
  { // video, audio and written content
    slug: 'fruit-that-lasts', title: 'Fruit That Lasts', speaker: 'Rev. Daniel Okafor', date: '2026-09-13T10:00',
    seriesSlug: 'rooted', scripture: 'John 15:1–17', featured: true, published: true,
    summary: 'Fruitfulness is not produced, it is grown — by staying connected to the vine.',
    thumbnail: '/assets/images/event-2.jpg',
    body: 'Roots grow in the dark, unseen — and so does character. Jesus\' last extended metaphor before the cross is a vineyard, and in it he gives us the whole architecture of the Christian life: a vine, branches, a gardener, and fruit.\n\nNotice what the branch is never asked to do: strain. The branch has one job — remain. Every verb of effort in this passage belongs to the gardener. Our culture tells us to produce; Jesus invites us to abide, and promises the producing will follow.\n\nSo the question this week is not "what are you achieving?" but "where are you attached?" Fruit that lasts grows from connection that lasts.',
    media: [
      { type: 'video', title: 'Watch the sermon', src: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', img: '/assets/images/event-2.jpg' },
      { type: 'audio', title: 'Listen', src: 'https://www.w3schools.com/html/horse.mp3', img: '' },
    ],
    attachments: [
      { name: 'Printable notes (PDF)', size: '240 KB', url: '#' },
      { name: 'Small group questions (PDF)', size: '180 KB', url: '#' },
    ],
  },
  { // audio only
    slug: 'planted-in-community', title: 'Planted in Community', speaker: 'Grace Lindqvist', date: '2026-08-30T10:00',
    seriesSlug: 'rooted', scripture: 'Acts 2:42–47', featured: false, published: true,
    summary: 'Nobody grows alone — the first church devoted themselves to four things, all of them together.',
    thumbnail: '/assets/images/welcome.jpg', body: '',
    media: [{ type: 'audio', title: 'Listen', src: 'https://www.w3schools.com/html/horse.mp3', img: '' }],
  },
  { // written content only
    slug: 'good-soil', title: 'Good Soil', speaker: 'Samuel Reyes', date: '2026-08-23T10:00',
    seriesSlug: 'rooted', scripture: 'Mark 4:1–20', featured: false, published: true,
    summary: 'The sower\'s seed never changes — the soil does. A written reflection on receptive hearts.',
    thumbnail: '/assets/images/gallery-6.jpg',
    body: 'Four soils, one seed. The parable of the sower is really a parable of soils — the seed is constant, generous, almost recklessly scattered. What varies is the ground it lands on.\n\nThe path is the hardened heart: truth bounces. The rocks are the shallow heart: enthusiasm without depth. The thorns are the crowded heart — and Jesus names the thorns precisely: worries, wealth, and wanting. Not evil things. Just choking things.\n\nGood soil, it turns out, is not perfect soil. It is broken-up, weeded, attended-to soil. Which is hopeful news: soil can be worked.',
    media: [],
    attachments: [{ name: 'Reading plan — Mark (PDF)', size: '95 KB', url: '#' }],
  },
]

// CMS-first: "Sermons" / "Sermon Series" collections feed these; the
// authored rows are the seed + offline fallback.
export const useSermonSeries = (): SermonSeries[] => {
  const rows = (useCms().items('sermonSeries', []) as any[]).filter(s => s.name)
    // a series added in the CMS with just a name gets its slug from the name
    // (the CMS's Series dropdown derives the same one)
    .map(s => ({ ...s, slug: String(s.slug ?? '').trim() || slugify(s.name) }))
  return (rows.length || useCms().isSite) ? rows as SermonSeries[] : series
}
export const useSermons = (): Sermon[] => {
  const rows = (useCms().items('sermons', []) as any[]).filter(s => s.title)
  const list = (rows.length || useCms().isSite) ? rows.map(s => ({ ...sermons.find(a => a.slug && a.slug === s.slug), ...s })) : sermons
  return list.map(normaliseContent).filter(isLive) as Sermon[]
}

const slugify = (t: string) => String(t).toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '')
  .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
const youtubeId = (url: string) => url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{6,})/)?.[1]

/** CMS toggles arrive as booleans, "1"/"0" or "true"/"false"; a row without the field counts as published */
export const isOn = (v: unknown, fallback = true) =>
  v === undefined || v === null || v === '' ? fallback : v === true || v === 1 || v === '1' || v === 'true' || v === 'yes'

/** Published and its publish time has come. */
export const isLive = (r: { published?: unknown, date?: string }) =>
  isOn(r.published) && !(r.date && new Date(r.date).getTime() > Date.now())

/**
 * Shared shape for CMS content rows (sermons, studies, songs): a slug from
 * the title when hidden/empty, the content split into paragraphs, and the
 * media rows mapped to the first video (YouTube link or uploaded file) and
 * the first audio recording.
 */
export const normaliseContent = (r: any) => {
  const media: ContentMedia[] = (Array.isArray(r.media) ? r.media : []).filter((m: any) => m && m.src)
  const video = media.find(m => m.type === 'video')
  const audio = media.find(m => m.type === 'audio')
  const yt = video ? youtubeId(video.src) : undefined
  const body = Array.isArray(r.body) ? r.body : String(r.body ?? '').split(/\n\s*\n/)
  return {
    ...r,
    slug: String(r.slug ?? '').trim() || slugify(r.title),
    published: isOn(r.published),
    featured: isOn(r.featured, false),
    body: body.map((p: string) => String(p).trim()).filter(Boolean),
    media,
    videoId: yt ?? r.videoId,
    videoFile: video && !yt ? video.src : undefined,
    audioUrl: audio?.src ?? r.audioUrl,
    poster: video?.img || r.poster,
  }
}

/** "13 September 2026" */
export const publishedOn = (iso?: string) => {
  const d = iso ? new Date(iso) : null
  return d && !Number.isNaN(d.getTime()) ? d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : (iso ?? '')
}

export const sermonSeriesOf = (s: Sermon) => useSermonSeries().find(x => x.slug === s.seriesSlug)
export const sermonThumb = (s: Sermon) =>
  s.thumbnail || sermonSeriesOf(s)?.cover || '/assets/images/welcome.jpg'
export const sermonMedia = (s: Sermon): ('video' | 'audio' | 'text')[] => [
  ...(s.videoId || s.videoFile ? ['video' as const] : []),
  ...(s.audioUrl ? ['audio' as const] : []),
  ...(s.body?.length ? ['text' as const] : []),
]
export const mediaIcon = { video: '▶', audio: '🎧', text: '✍' } as const
