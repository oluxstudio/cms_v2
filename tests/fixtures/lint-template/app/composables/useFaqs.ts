type Faq = { q: string; a: string }
const faqs: Faq[] = [
  { q: 'When are services?', a: 'Every Sunday at 9:00 AM and 11:00 AM in the main hall.' },
  { q: 'Is parking free?', a: 'Yes — the main car park is free for all visitors on Sundays.' },
  { q: 'Can I bring children?', a: 'Absolutely; kids church runs during both morning services.' },
]
export const useFaqs = () => faqs
