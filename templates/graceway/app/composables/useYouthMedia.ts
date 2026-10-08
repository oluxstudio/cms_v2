import type { GalleryItem } from './useGalleryTypes'

// Youth gallery — the youth rows of the shared Media library (useMediaLibrary).
export const useYouthMedia = (): GalleryItem[] => useMediaLibrary('youth')
