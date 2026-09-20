export type MediaType = 'image' | 'video' | 'audio'

export interface GalleryItem {
  /** CMS collection item id + collection id — present when CMS-fed (beacons) */
  id?: string
  _cid?: string
  type: MediaType
  img?: string
  src?: string
  title: string
  cat: string
  date: string
}

export const slugify = (s: string) =>
  s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
