export interface Song {
  title: string
  artist: string
  /** listen link — YouTube/Spotify */
  url: string
}

// "What we're singing" — the current season's set list.
// The worship leader rotates this list; links let the congregation listen midweek.
const fallback: Song[] = [
  { title: 'Goodness of God', artist: 'Bethel Music', url: 'https://www.youtube.com/results?search_query=goodness+of+god' },
  { title: 'Way Maker', artist: 'Sinach', url: 'https://www.youtube.com/results?search_query=way+maker+sinach' },
  { title: 'Great Is Thy Faithfulness', artist: 'Hymn · arr. CAC Choir', url: 'https://www.youtube.com/results?search_query=great+is+thy+faithfulness' },
  { title: 'Firm Foundation (He Won\'t)', artist: 'Cody Carnes', url: 'https://www.youtube.com/results?search_query=firm+foundation+cody+carnes' },
  { title: 'Igwe', artist: 'Midnight Crew', url: 'https://www.youtube.com/results?search_query=igwe+midnight+crew' },
  { title: 'Build My Life', artist: 'Housefires', url: 'https://www.youtube.com/results?search_query=build+my+life' },
]

export const useSongs = (): Song[] => {
  const { items } = useCms()
  const rows = (items('songs', []) as any[]).filter(s => s.title && s.url)
  return rows.length ? rows as Song[] : fallback
}
