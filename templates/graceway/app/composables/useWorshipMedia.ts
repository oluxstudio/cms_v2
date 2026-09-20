import type { GalleryItem } from './useGalleryTypes'

// Worship & praise gallery media — sample video/audio sources until real recordings land.
const fallback: GalleryItem[] = [
  { type: 'video', img: '/assets/images/event-2.jpg', src: 'https://www.w3schools.com/html/mov_bbb.mp4', title: 'Sunday worship recap', cat: 'Sunday Service', date: '2026-09-07' },
  { type: 'image', img: '/assets/images/gallery-3.jpg', title: 'Hands raised', cat: 'Sunday Service', date: '2026-09-07' },
  { type: 'audio', src: 'https://www.w3schools.com/html/horse.mp3', title: 'Choir set (live)', cat: 'Choir', date: '2026-08-31' },
  { type: 'image', img: '/assets/images/gallery-5.jpg', title: 'Youth choir joins', cat: 'Choir', date: '2026-08-31' },
  { type: 'video', img: '/assets/images/event-3.jpg', src: 'https://www.w3schools.com/html/mov_bbb.mp4', title: 'Worship night — candlelight', cat: 'Worship Night', date: '2026-08-22' },
  { type: 'image', img: '/assets/images/gallery-9.jpg', title: 'Band in flow', cat: 'Band', date: '2026-08-17' },
  { type: 'image', img: '/assets/images/circle-1.jpg', title: 'Leading from the pulpit', cat: 'Sunday Service', date: '2026-08-10' },
  { type: 'audio', src: 'https://www.w3schools.com/html/horse.mp3', title: 'Hymn medley', cat: 'Choir', date: '2026-08-03' },
  { type: 'image', img: '/assets/images/gallery-10.jpg', title: 'Prayer after worship', cat: 'Sunday Service', date: '2026-07-27' },
  { type: 'video', img: '/assets/images/gallery-3.jpg', src: 'https://www.w3schools.com/html/mov_bbb.mp4', title: 'First-Friday worship night', cat: 'Worship Night', date: '2026-07-04' },
  { type: 'image', img: '/assets/images/pastor-2.jpg', title: 'Grace leading praise', cat: 'Band', date: '2026-06-29' },
  { type: 'image', img: '/assets/images/circle-2.jpg', title: 'A quiet moment', cat: 'Worship Night', date: '2026-06-06' },
  { type: 'audio', src: 'https://www.w3schools.com/html/horse.mp3', title: 'Way Maker (congregation)', cat: 'Sunday Service', date: '2026-05-31' },
  { type: 'image', img: '/assets/images/event-2.jpg', title: 'Full sanctuary', cat: 'Sunday Service', date: '2026-05-24' },
]

// CMS-first: the site's "Media" collection feeds this (ministry-filtered);
// the authored rows above keep the pristine template pixel-identical and
// serve as the seed data for new sites.
export const useWorshipMedia = (): GalleryItem[] => {
  const { items } = useCms()
  const rows = (items('media', []) as any[])
    .filter(m => (m.ministry || 'worship') === 'worship' && m.title && m.type)
    .map(m => ({ id: m.id, _cid: m._cid, type: m.type, img: m.img || undefined, src: m.src || undefined,
               title: m.title, cat: m.cat || 'General', date: m.date || '' }) as GalleryItem)
  return rows.length ? rows : fallback
}
