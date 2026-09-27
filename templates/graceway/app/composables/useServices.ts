export type Service = {
  label: string
  day: string
  time: string
}

// Single source of truth for service days & times (every time mention derives from here).
/** @olux-collection Services */
const services: Service[] = [
  { label: 'Sunday Worship', day: 'Sunday', time: '10:00 AM' },
  { label: 'Wednesday Prayer', day: 'Wednesday', time: '7:00 PM' },
  { label: 'Bible Study', day: 'Friday', time: '9:00 PM' },
  { label: 'Youth Gathering', day: 'Friday', time: '6:30 PM' },
]

// CMS-first: the "Services" collection overrides the authored rows at runtime.
export const useServices = (): Service[] => {
  const rows = (useCms().items('services', []) as any[]).filter(s => s.label && s.time)
  return rows.length ? rows.map(s => ({ day: '', ...s }) as Service) : services
}
 