// Shared by the ministry pages' sidebar components (Ministry Leaders, Events,
// Verse, Prayer): each is its own block, rendered into its slot in the
// Ministry Hub's sidebar.

/** This block's id on the current page — the key of its own "Items in this block" selection. */
export const usePageBlockId = (blockKey: string) => {
  const route = useRoute()
  const { data } = ensureOluxContent()
  return computed(() => {
    const path = route.path.replace(/\/+$/, '') || '/'
    const pages: any[] = data.value?.pages ?? []
    const page = pages.find(p => ((p.url || '/').replace(/\/+$/, '') || '/') === path)
    return String((page?.wireframe ?? []).find((b: any) => String(b.type || '').endsWith(`:${blockKey}`))?.id ?? '')
  })
}

/** True once the Ministry Hub's sidebar slot (#{slotId}) is on the page — teleport into it, else render in place. */
export const useSidebarSlot = (slotId: string) => {
  const ready = ref(false)
  onMounted(() => { ready.value = !!document.getElementById(slotId) })
  return ready
}

/** The ministry page's event type (men's page → "men" …). */
export const MINISTRY_EVENT_TYPE: Record<string, string> = { '/mens-ministry': 'men', '/womens-ministry': 'women', '/youth': 'youth' }

/** True inside Edit site's preview (?olx-edit=1): empty cards show a placeholder there so they can still be clicked and filled. */
export const useEditMode = () => {
  const on = ref(false)
  onMounted(() => { on.value = new URLSearchParams(window.location.search).get('olx-edit') === '1' })
  return on
}
