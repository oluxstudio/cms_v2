// Resolve any media URL — local file, external file, or a platform page link
// (YouTube / Facebook / TikTok) — into what the MediaPlayer should mount.
export type MediaSourceKind = 'youtube' | 'facebook' | 'tiktok' | 'video' | 'audio'

export interface ResolvedMediaSource {
  kind: MediaSourceKind
  /** iframe URL for platform embeds; undefined for plain files */
  embed?: string
}

const AUDIO_EXT = /\.(mp3|m4a|wav|ogg|aac|flac)(\?.*)?$/i

export const resolveMediaSource = (src: string, hint?: 'video' | 'audio'): ResolvedMediaSource => {
  const url = (src || '').trim()

  // YouTube: watch?v=ID, youtu.be/ID, shorts/ID, live/ID, or an /embed/ URL
  const yt = url.match(/(?:youtube(?:-nocookie)?\.com\/(?:watch\?[^#]*v=|shorts\/|live\/|embed\/)|youtu\.be\/)([\w-]{6,})/)
  if (yt) return { kind: 'youtube', embed: `https://www.youtube-nocookie.com/embed/${yt[1]}?autoplay=1&rel=0` }

  // Facebook: any video page (videos/, watch/?v=, reel/) or fb.watch short link
  if (/(?:facebook\.com\/(?:.*\/videos\/|watch|reel\/)|fb\.watch\/)/.test(url)) {
    return { kind: 'facebook', embed: `https://www.facebook.com/plugins/video.php?href=${encodeURIComponent(url)}&autoplay=1&show_text=false` }
  }

  // TikTok: tiktok.com/@user/video/{id}
  const tk = url.match(/tiktok\.com\/@[\w.-]+\/video\/(\d+)/)
  if (tk) return { kind: 'tiktok', embed: `https://www.tiktok.com/embed/v2/${tk[1]}` }

  // Plain file — extension (or the caller's hint) decides audio vs video
  if (hint === 'audio' || (!hint && AUDIO_EXT.test(url))) return { kind: 'audio' }
  return { kind: 'video' }
}
