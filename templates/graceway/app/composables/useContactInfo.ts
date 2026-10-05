export type ContactInfoRow = {
  icon: string
  label: string
  /** display text; multi-line rows (office hours) join lines with \n */
  value: string
  href?: string
}

// Seed rows mirror the site profile; once applied, the CMS collection is the
// editable source for the contact card.
/** @olux-collection Contact Info */
const rows: ContactInfoRow[] = [
  { icon: '📱', label: 'Phone Number', value: '+1 705 55 50 000', href: 'tel:+17055550000' },
  { icon: '✉️', label: 'Email Address', value: 'hello@cacblackburn.org', href: 'mailto:hello@cacblackburn.org' },
  { icon: '🕐', label: 'Office Hours', value: 'Mon – Fri · 9:00 AM – 4:00 PM' },
  { icon: '📍', label: 'Our Location', value: '85 Johnston Street' },
]

// CMS-first: the "Contact Info" collection overrides the authored rows at runtime.
export const useContactInfo = (): ContactInfoRow[] => {
  const cms = (useCms().items('contactInfo', []) as any[]).filter(r => r.label && r.value)
  return (cms.length || useCms().isSite) ? (cms as ContactInfoRow[]) : rows
}
