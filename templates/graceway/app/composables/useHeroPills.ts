export type HeroPill = {
  key: string
  label: string
  desc: string
  /** page the pill opens */
  to: string
  img: string
  tint: string
}

// Single source of truth for the home hero's photo pills.
/** @olux-collection Hero Pills */
const pills: HeroPill[] = [
  { key: 'store', label: 'Stores', desc: 'Books, music and church merchandise — every purchase supports our outreach.', to: '/store', img: '/assets/images/circle-1.jpg', tint: 'tint-yellow' },
  { key: 'events', label: 'Events', desc: 'Picnics, food drives, worship nights — life together beyond Sunday.', to: '/events', img: '/assets/images/event-1.jpg', tint: 'tint-red' },
  { key: 'bible', label: 'Bible Study', desc: 'Midweek studies and small groups digging deeper into the Word.', to: '/bible-study', img: '/assets/images/circle-2.jpg', tint: 'tint-gold' },
  { key: 'sermons', label: 'Sermons & Blog', desc: 'Catch up on recent messages and read reflections from our pastors.', to: '/sermons', img: '/assets/images/event-2.jpg', tint: 'tint-blue' },
  { key: 'youth', label: 'Youth Ministry', desc: 'A place for teens to ask big questions and build real friendships.', to: '/youth', img: '/assets/images/circle-3.jpg', tint: 'tint-teal' },
  { key: 'worship', label: 'Worship', desc: 'Join us Sundays for music, prayer and teaching.', to: '/worship', img: '/assets/images/event-3.jpg', tint: 'tint-green' },
]

// CMS-first: the "Hero Pills" collection overrides the authored rows at runtime.
export const useHeroPills = (): HeroPill[] => {
  const rows = (useCms().items('heroPills', []) as any[]).filter(p => p.label && p.to)
  return (rows.length || useCms().isSite) ? rows.map((p, i) => ({ key: p.key || `pill-${i}`, ...p }) as HeroPill) : pills
}
