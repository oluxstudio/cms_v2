import type { GalleryItem } from './useGalleryTypes'
import { slugify } from './useGalleryTypes'

export interface MediaEntry extends GalleryItem {
  slug: string
}

// every playable/browsable media item across the site, with stable slugs
export const useAllMedia = (): MediaEntry[] => {
  const all = [...useYouthMedia(), ...useWorshipMedia()]
  const seen = new Set<string>()
  return all
    .map(m => ({ ...m, slug: slugify(`${m.title}-${m.date}`) }))
    .filter(m => !seen.has(m.slug) && seen.add(m.slug))
}
