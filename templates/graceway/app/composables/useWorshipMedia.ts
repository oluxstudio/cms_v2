import type { GalleryItem } from './useGalleryTypes'

// Worship gallery — the worship rows of the shared Media library (useMediaLibrary).
export const useWorshipMedia = (): GalleryItem[] => useMediaLibrary('worship')
