// Submits an authored template form into its CMS form
// (POST /api/sites/{site}/form/{name}) so entries land in the owner's
// Messages/Forms inbox with email routing. Includes the `_hp` honeypot
// field; falls back to a friendly error when the CMS is unreachable.
/** Visitor device/context summary — stored when the CMS form defines a `device` field. */
export const deviceDetails = (): string => {
  try {
    const ua = navigator.userAgent
    const kind = /Mobi|Android/i.test(ua) ? 'Mobile' : /iPad|Tablet/i.test(ua) ? 'Tablet' : 'Desktop'
    return [
      kind,
      `${window.screen.width}×${window.screen.height}`,
      navigator.language,
      Intl.DateTimeFormat().resolvedOptions().timeZone,
      ua,
    ].join(' · ')
  } catch { return '' }
}

export function useCmsForm(formName: string) {
  const sending = ref(false)
  const error = ref('')

  const submit = async (fields: Record<string, any>): Promise<boolean> => {
    sending.value = true
    error.value = ''
    try {
      const pub: any = (useRuntimeConfig() as any).public || {}
      const site = pub.cmsSite || pub.oluxSite || 'graceway'
      const base = pub.bookingApiBase || pub.cmsApiBase || ''
      const res = await fetch(`${base || window.location.origin}/api/sites/${encodeURIComponent(site)}/form/${formName}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ device: deviceDetails(), page: window.location.pathname, ...fields, _hp: '' }),
      })
      if (!res.ok) {
        const body = await res.json().catch(() => ({} as any))
        error.value = body.message || 'Something went wrong — please try again.'
        return false
      }
      return true
    } catch {
      error.value = 'Could not reach the server — please try again.'
      return false
    } finally {
      sending.value = false
    }
  }

  return { submit, sending, error }
}
