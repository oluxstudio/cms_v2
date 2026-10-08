import type { ContentMedia } from './useSermons'

export interface Song {
  slug: string
  title: string
  /** the author / artist */
  artist: string
  /** a short description — why we sing it, when it's used */
  description?: string
  /** the lyrics (content) */
  lyrics?: string
  /** listen link — YouTube/Spotify */
  url: string
  /** recordings / videos from Assets */
  media?: ContentMedia[]
  /** publish date & time, ISO */
  date?: string
  published?: boolean
}

// "What we're singing" — the current season's set list.
// The worship leader rotates this list; links let the congregation listen midweek.
/** @olux-collection Songs
 * @olux-field slug text hidden
 * @olux-field title textarea2 required
 * @olux-field artist text label="Author / artist"
 * @olux-field description textarea6 label="Description"
 * @olux-field lyrics textarea15 label="Lyrics"
 * @olux-field media rows label="Media"
 * @olux-field media.type select options=audio|video
 * @olux-field media.title text
 * @olux-field media.src media label="File from Assets (or a YouTube link)"
 * @olux-field media.img image label="Poster image"
 * @olux-field url url label="Listen link (YouTube / Spotify)"
 * @olux-field date datetime label="Publish date & time"
 * @olux-field published toggle label="Published"
 */
const fallback: Song[] = [
  { slug: 'goodness-of-god', title: 'Goodness of God', artist: 'Bethel Music', description: 'Our opening song this season — a testimony of God\'s faithfulness through every season of life.', lyrics: '', media: [{ type: 'audio', title: 'Choir rehearsal recording', src: 'https://www.w3schools.com/html/horse.mp3', img: '' }], url: 'https://www.youtube.com/results?search_query=goodness+of+god', date: '2026-09-01T09:00', published: true },
  { slug: 'way-maker', title: 'Way Maker', artist: 'Sinach', description: 'A Nigerian worship anthem the whole congregation knows by heart.', lyrics: '', media: [], url: 'https://www.youtube.com/results?search_query=way+maker+sinach', date: '2026-09-01T09:00', published: true },
  { slug: 'great-is-thy-faithfulness', title: 'Great Is Thy Faithfulness', artist: 'Hymn · arr. CAC Choir', description: 'The classic hymn in the choir\'s own arrangement.', lyrics: '', media: [], url: 'https://www.youtube.com/results?search_query=great+is+thy+faithfulness', date: '2026-09-01T09:00', published: true },
  { slug: 'firm-foundation', title: 'Firm Foundation (He Won\'t)', artist: 'Cody Carnes', description: '', lyrics: '', media: [], url: 'https://www.youtube.com/results?search_query=firm+foundation+cody+carnes', date: '2026-09-01T09:00', published: true },
  { slug: 'igwe', title: 'Igwe', artist: 'Midnight Crew', description: '', lyrics: '', media: [], url: 'https://www.youtube.com/results?search_query=igwe+midnight+crew', date: '2026-09-01T09:00', published: true },
  { slug: 'build-my-life', title: 'Build My Life', artist: 'Housefires', description: '', lyrics: '', media: [], url: 'https://www.youtube.com/results?search_query=build+my+life', date: '2026-09-01T09:00', published: true },
]

/** Published songs whose publish time has come (CMS rows; authored rows only in a template preview). */
export const useSongs = (): Song[] => {
  const { items } = useCms()
  const rows = (items('songs', []) as any[]).filter(s => s.title)
  const list: any[] = (rows.length || useCms().isSite) ? rows : fallback
  return list.map((s) => {
    const media: ContentMedia[] = (Array.isArray(s.media) ? s.media : []).filter((m: any) => m && m.src)
    return {
      ...s,
      slug: String(s.slug ?? '').trim() || String(s.title).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''),
      published: isOn(s.published),
      media,
      url: s.url || media[0]?.src || '',
    } as Song
  }).filter(isLive)
}
