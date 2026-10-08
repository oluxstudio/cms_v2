import type { GalleryItem } from './useGalleryTypes'

// One media library for every gallery (worship, youth): photos, videos and
// recordings, each tagged with the ministry whose gallery shows it.
/** @olux-collection Media
 * @olux-field ministry select options=worship|youth label="Gallery"
 * @olux-field type select options=image|video|audio
 * @olux-field title text required
 * @olux-field img image label="Image / poster"
 * @olux-field src media label="Video or audio file (from Assets)" show=type:video|audio
 * @olux-field cat text label="Category"
 * @olux-field date date
 */
const library: (GalleryItem & { ministry: 'worship' | 'youth' })[] = [
  { ministry: 'worship', type: 'video', img: '/assets/images/event-2.jpg', src: 'https://www.w3schools.com/html/mov_bbb.mp4', title: 'Sunday worship recap', cat: 'Sunday Service', date: '2026-09-07' },
  { ministry: 'worship', type: 'image', img: '/assets/images/gallery-3.jpg', src: '', title: 'Hands raised', cat: 'Sunday Service', date: '2026-09-07' },
  { ministry: 'worship', type: 'audio', img: '', src: 'https://www.w3schools.com/html/horse.mp3', title: 'Choir set (live)', cat: 'Choir', date: '2026-08-31' },
  { ministry: 'worship', type: 'image', img: '/assets/images/gallery-5.jpg', src: '', title: 'Youth choir joins', cat: 'Choir', date: '2026-08-31' },
  { ministry: 'worship', type: 'video', img: '/assets/images/event-3.jpg', src: 'https://www.w3schools.com/html/mov_bbb.mp4', title: 'Worship night — candlelight', cat: 'Worship Night', date: '2026-08-22' },
  { ministry: 'worship', type: 'image', img: '/assets/images/gallery-9.jpg', src: '', title: 'Band in flow', cat: 'Band', date: '2026-08-17' },
  { ministry: 'worship', type: 'image', img: '/assets/images/circle-1.jpg', src: '', title: 'Leading from the pulpit', cat: 'Sunday Service', date: '2026-08-10' },
  { ministry: 'worship', type: 'audio', img: '', src: 'https://www.w3schools.com/html/horse.mp3', title: 'Hymn medley', cat: 'Choir', date: '2026-08-03' },
  { ministry: 'worship', type: 'image', img: '/assets/images/gallery-10.jpg', src: '', title: 'Prayer after worship', cat: 'Sunday Service', date: '2026-07-27' },
  { ministry: 'worship', type: 'video', img: '/assets/images/gallery-3.jpg', src: 'https://www.w3schools.com/html/mov_bbb.mp4', title: 'First-Friday worship night', cat: 'Worship Night', date: '2026-07-04' },
  { ministry: 'worship', type: 'image', img: '/assets/images/pastor-2.jpg', src: '', title: 'Grace leading praise', cat: 'Band', date: '2026-06-29' },
  { ministry: 'worship', type: 'image', img: '/assets/images/circle-2.jpg', src: '', title: 'A quiet moment', cat: 'Worship Night', date: '2026-06-06' },
  { ministry: 'worship', type: 'audio', img: '', src: 'https://www.w3schools.com/html/horse.mp3', title: 'Way Maker (congregation)', cat: 'Sunday Service', date: '2026-05-31' },
  { ministry: 'worship', type: 'image', img: '/assets/images/event-2.jpg', src: '', title: 'Full sanctuary', cat: 'Sunday Service', date: '2026-05-24' },
  { ministry: 'youth', type: 'image', img: '/assets/images/gallery-1.jpg', src: '', title: 'Friday games', cat: 'Youth Night', date: '2026-09-04' },
  { ministry: 'youth', type: 'video', img: '/assets/images/gallery-3.jpg', src: 'https://www.w3schools.com/html/mov_bbb.mp4', title: 'Worship night recap', cat: 'Worship', date: '2026-09-01' },
  { ministry: 'youth', type: 'image', img: '/assets/images/gallery-2.jpg', src: '', title: 'Word together', cat: 'Bible Study', date: '2026-08-28' },
  { ministry: 'youth', type: 'audio', img: '', src: 'https://www.w3schools.com/html/horse.mp3', title: 'Testimony: Amara', cat: 'Youth Night', date: '2026-08-25' },
  { ministry: 'youth', type: 'image', img: '/assets/images/gallery-4.jpg', src: '', title: 'Small group', cat: 'Bible Study', date: '2026-08-21' },
  { ministry: 'youth', type: 'image', img: '/assets/images/gallery-5.jpg', src: '', title: 'Youth choir', cat: 'Worship', date: '2026-08-16' },
  { ministry: 'youth', type: 'video', img: '/assets/images/gallery-7.jpg', src: 'https://www.w3schools.com/html/mov_bbb.mp4', title: 'Camp highlights', cat: 'Events', date: '2026-08-15' },
  { ministry: 'youth', type: 'image', img: '/assets/images/gallery-6.jpg', src: '', title: 'Open Bibles', cat: 'Bible Study', date: '2026-08-14' },
  { ministry: 'youth', type: 'audio', img: '', src: 'https://www.w3schools.com/html/horse.mp3', title: 'Youth worship set (live)', cat: 'Worship', date: '2026-08-10' },
  { ministry: 'youth', type: 'image', img: '/assets/images/gallery-8.jpg', src: '', title: 'Study notes', cat: 'Bible Study', date: '2026-07-31' },
  { ministry: 'youth', type: 'image', img: '/assets/images/gallery-9.jpg', src: '', title: 'Worship set', cat: 'Worship', date: '2026-07-26' },
  { ministry: 'youth', type: 'video', img: '/assets/images/gallery-11.jpg', src: 'https://www.w3schools.com/html/mov_bbb.mp4', title: 'Serve day diary', cat: 'Events', date: '2026-07-22' },
  { ministry: 'youth', type: 'image', img: '/assets/images/gallery-10.jpg', src: '', title: 'Prayer circle', cat: 'Worship', date: '2026-07-19' },
  { ministry: 'youth', type: 'image', img: '/assets/images/gallery-12.jpg', src: '', title: 'Deep dive', cat: 'Bible Study', date: '2026-07-03' },
  { ministry: 'youth', type: 'audio', img: '', src: 'https://www.w3schools.com/html/horse.mp3', title: 'Q&A night: identity', cat: 'Bible Study', date: '2026-06-30' },
  { ministry: 'youth', type: 'image', img: '/assets/images/circle-1.jpg', src: '', title: 'At the pulpit', cat: 'Worship', date: '2026-06-28' },
  { ministry: 'youth', type: 'image', img: '/assets/images/circle-2.jpg', src: '', title: 'In prayer', cat: 'Worship', date: '2026-06-21' },
  { ministry: 'youth', type: 'image', img: '/assets/images/circle-3.jpg', src: '', title: 'Youth smiles', cat: 'Youth Night', date: '2026-06-12' },
  { ministry: 'youth', type: 'video', img: '/assets/images/event-2.jpg', src: 'https://www.w3schools.com/html/mov_bbb.mp4', title: 'On stage', cat: 'Events', date: '2026-06-08' },
  { ministry: 'youth', type: 'image', img: '/assets/images/event-1.jpg', src: '', title: 'Picnic day', cat: 'Events', date: '2026-06-06' },
  { ministry: 'youth', type: 'image', img: '/assets/images/event-3.jpg', src: '', title: 'Candlelight', cat: 'Events', date: '2026-05-24' },
  { ministry: 'youth', type: 'image', img: '/assets/images/welcome.jpg', src: '', title: 'All together', cat: 'Events', date: '2026-05-17' },
  { ministry: 'youth', type: 'audio', img: '', src: 'https://www.w3schools.com/html/horse.mp3', title: 'Camp stories', cat: 'Events', date: '2026-05-12' },
  { ministry: 'youth', type: 'image', img: '/assets/images/bible-study.jpg', src: '', title: 'Group study', cat: 'Bible Study', date: '2026-05-08' },
  { ministry: 'youth', type: 'image', img: '/assets/images/pastor-3.jpg', src: '', title: 'Youth pastor', cat: 'Youth Night', date: '2026-04-24' },
  { ministry: 'youth', type: 'image', img: '/assets/images/pastor-2.jpg', src: '', title: 'Leading praise', cat: 'Worship', date: '2026-04-17' },
]

/**
 * A ministry's gallery: the site's "Media" collection rows for it (the
 * authored library only in a template preview).
 */
export const useMediaLibrary = (ministry: 'worship' | 'youth'): GalleryItem[] => {
  const { items, isSite } = useCms()
  const all = items('media', []) as any[]
  const source: any[] = (all.length || isSite) ? all : library
  return source
    .filter(m => (m.ministry || 'worship') === ministry && m.title && m.type)
    .map(m => ({ id: m.id, _cid: m._cid, type: m.type, img: m.img || undefined, src: m.src || undefined,
               title: m.title, cat: m.cat || 'General', date: m.date || '' }) as GalleryItem)
}
