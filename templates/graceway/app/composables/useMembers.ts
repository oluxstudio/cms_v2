export interface Member {
  slug: string
  img: string
  name: string
  role: string
  bio: string
  verse: string
  email: string
  /** casual one-liner used in "meet the leaders" widgets */
  fact: string
}

export interface MemberProfileExtras {
  stats: { value: string, label: string }[]
  skills: { icon: string, name: string, pct: number }[]
}

// Shared profile extras rendered on every leadership profile page;
// per-member values can replace this when the CMS is wired up.
export const memberExtras: MemberProfileExtras = {
  stats: [
    { value: '15+', label: 'Years Serving' },
    { value: '6', label: 'Ministries Led' },
    { value: '1k+', label: 'Lives Touched' },
  ],
  skills: [
    { icon: '📖', name: 'Teaching & Preaching', pct: 95 },
    { icon: '🙏', name: 'Pastoral Care', pct: 90 },
    { icon: '🎵', name: 'Worship Leading', pct: 75 },
    { icon: '🤝', name: 'Community Outreach', pct: 85 },
    { icon: '👥', name: 'Small Groups', pct: 80 },
    { icon: '🧒', name: 'Youth & Children', pct: 70 },
  ],
}

/** @olux-collection Leadership */
const members: Member[] = [
  {
    slug: 'daniel-okafor', img: '/assets/images/pastor-1.jpg', name: 'Rev. Daniel Okafor', role: 'Senior Pastor',
    bio: 'Pastor Daniel has led CAC Blackburn for over fifteen years. A gifted teacher with a shepherd\'s heart, he is passionate about seeing every believer rooted in scripture and serving the city. He is married to Adaeze and they have three children.',
    verse: '"Let all that you do be done in love." — 1 Corinthians 16:14', email: 'daniel@cacblackburn.org', fact: 'Preaches better after two coffees. Scientifically proven.',
  },
  {
    slug: 'grace-lindqvist', img: '/assets/images/pastor-2.jpg', name: 'Grace Lindqvist', role: 'Worship & Music Director',
    bio: 'Grace directs our choirs, bands and production teams. She believes worship is for every voice — trained or not — and has built a music ministry where all skill levels find a place to serve.',
    verse: '"Sing to the Lord a new song." — Psalm 96:1', email: 'grace@cacblackburn.org', fact: 'Can harmonize with a fire alarm.',
  },
  {
    slug: 'samuel-reyes', img: '/assets/images/pastor-3.jpg', name: 'Samuel Reyes', role: 'Youth & Outreach Pastor',
    bio: 'Samuel leads our teens and our neighbourhood outreach. From Friday youth nights to Saturday food drives, he lives the belief that faith gets real when it crosses the street.',
    verse: '"Let no one despise you for your youth." — 1 Timothy 4:12', email: 'samuel@cacblackburn.org', fact: 'Undefeated at table tennis. Allegedly.',
  },
  {
    slug: 'esther-mwangi', img: '/assets/images/pastor-4.jpg', name: 'Esther Mwangi', role: 'Prayer Ministry Lead',
    bio: 'Esther leads the intercessors who pray for our church, our city and every request left in the prayer box. She hosts the Wednesday early morning watch in the chapel.',
    verse: '"Pray without ceasing." — 1 Thessalonians 5:17', email: 'esther@cacblackburn.org', fact: 'Up praying before your alarm has even thought about it.',
  },
  {
    slug: 'ruth-alonso', img: '/assets/images/pastor-5.jpg', name: 'Ruth Alonso', role: 'Children\'s Ministry Director',
    bio: 'Ruth oversees everything from the nursery to grade six — a safe, joyful world where kids discover faith through stories, songs and play while their parents worship.',
    verse: '"Let the little children come to me." — Matthew 19:14', email: 'ruth@cacblackburn.org', fact: 'Brings the good snacks. Every time.',
  },
  {
    slug: 'peter-adeyemi', img: '/assets/images/pastor-6.jpg', name: 'Peter Adeyemi', role: 'Missions & Community Pastor',
    bio: 'Peter coordinates our mission partnerships abroad and our community projects at home, from the food pantry to shelter support. He is happiest with his sleeves rolled up.',
    verse: '"Go into all the world." — Mark 16:15', email: 'peter@cacblackburn.org', fact: 'Owns more hi-vis vests than shirts.',
  },
]

// CMS-first: the site's "Leadership" collection feeds every profile;
// authored rows keep the pristine template identical and seed new sites.
export const useMembers = (): Member[] => {
  const { items } = useCms()
  const rows = (items('leadership', []) as any[]).filter(m => m.name && m.slug)
  return rows.length ? rows.map(m => ({ ...members.find(a => a.slug === m.slug), ...m }) as Member) : members
}
