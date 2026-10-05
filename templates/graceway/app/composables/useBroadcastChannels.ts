/** per-platform extras layered over the global `socials` entry with the same key */
export type BroadcastChannelExtra = {
  key: string
  handle: string
  followers: string
  live: boolean
  color: string
  watch: string
  blurb: string
}

// Single source of truth for the /broadcast platform pills.
/** @olux-collection Broadcast Channels */
const channels: BroadcastChannelExtra[] = [
  { key: 'youtube', handle: '@cacblackburn', color: '#d1242f', followers: '12.4k subscribers', live: true,
    watch: 'https://youtube.com/@cacblackburn/live',
    blurb: 'Full Sunday services, sermon replays and worship nights in HD.' },
  { key: 'facebook', handle: 'CAC Blackburn', color: '#1d5fd1', followers: '8.9k followers', live: true,
    watch: 'https://facebook.com/cacblackburn/live',
    blurb: 'Live services with real-time chat, event updates and photo albums.' },
  { key: 'instagram', handle: '@cacblackburn', color: '#c13584', followers: '6.2k followers', live: false,
    watch: 'https://instagram.com/cacblackburn',
    blurb: 'Daily encouragement, behind-the-scenes moments and reels from Sunday.' },
  { key: 'tiktok', handle: '@cacblackburn', color: '#14181d', followers: 'Coming soon', live: false,
    watch: 'https://tiktok.com/@cacblackburn/live',
    blurb: 'Short worship clips and testimonies — launching this season.' },
]

// CMS rows store checkbox values as strings
const asBool = (v: any) => typeof v === 'boolean' ? v : v === 'true' || v === '1' || v === 1

// CMS-first: the "Broadcast Channels" collection overrides the authored rows at runtime.
export const useBroadcastChannels = (): BroadcastChannelExtra[] => {
  const rows = (useCms().items('broadcastChannels', []) as any[]).filter(c => c.key)
  const list = (rows.length || useCms().isSite) ? (rows as BroadcastChannelExtra[]) : channels
  return list.map(c => ({ ...c, live: asBool(c.live) }))
}
