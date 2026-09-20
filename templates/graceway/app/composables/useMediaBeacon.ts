// Fire-and-forget engagement beacons for collection-backed media items.
// The owner sees per-item view/play analytics on the CMS Collections page.
export const useMediaBeacon = () => {
  const send = (item: any, event: 'view' | 'play') => {
    try {
      if (!item?.id || !item?._cid || typeof window === 'undefined') return
      const guard = `olux-media-${event}-${item.id}`
      if (sessionStorage.getItem(guard)) return
      sessionStorage.setItem(guard, '1')
      const { site, apiBase } = useOluxSite()
      $fetch(`${apiBase || window.location.origin}/api/sites/${encodeURIComponent(site)}/collections/${item._cid}/items/${item.id}/event`, {
        method: 'POST',
        body: { event },
      }).catch(() => {})
    } catch (_) {}
  }
  return { send }
}
