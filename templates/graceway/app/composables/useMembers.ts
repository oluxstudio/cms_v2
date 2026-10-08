export interface Member {
  slug: string
  img: string
  /** shown before the name — "Rev.", "Pastor", "Deaconess" … (optional) */
  title?: string
  name: string
  role: string
  bio: string
  /** the profile page's "About" section (the bio when empty) */
  about?: string
  verse: string
  email: string
  /** casual one-liner used in "meet the leaders" widgets */
  fact: string
  /** personal social accounts — any number per member (0 is fine);
      `key` matches an entry in the global socials data source (icon/color) */
  socials?: { key: string; href: string }[]
  /** ministries they lead — names as in the Ministries menu (e.g. "Men's Ministry");
      they appear in that ministry page's sidebar */
  ministries?: string[]
  /** numbers on the profile page ("15+ Years Serving") — the shared Profile Stats when empty */
  stats?: { value: string, label: string }[]
  /** "Ministry Focus" cards on the profile page — the shared Profile Skills when empty */
  focus?: { icon: string, name: string }[]
}

export interface MemberProfileExtras {
  stats: { value: string, label: string }[]
  skills: { icon: string, name: string, pct: number }[]
}

// Shared profile extras rendered on every leadership profile page;
// per-member values can replace this when the CMS is wired up.
/** @olux-collection Profile Stats
 * @olux-field value text label="Number (e.g. 15+)" required
 * @olux-field label text label="Label (e.g. Years Serving)" required
 */
const profileStats: MemberProfileExtras['stats'] = [
    { value: '15+', label: 'Years Serving' },
    { value: '6', label: 'Ministries Led' },
    { value: '1k+', label: 'Lives Touched' },
  ]
/** @olux-collection Profile Skills
 * @olux-field icon text label="Icon (an emoji)"
 * @olux-field name text label="Focus area" required
 */
const profileSkills: MemberProfileExtras['skills'] = [
    { icon: '📖', name: 'Teaching & Preaching', pct: 95 },
    { icon: '🙏', name: 'Pastoral Care', pct: 90 },
    { icon: '🎵', name: 'Worship Leading', pct: 75 },
    { icon: '🤝', name: 'Community Outreach', pct: 85 },
    { icon: '👥', name: 'Small Groups', pct: 80 },
    { icon: '🧒', name: 'Youth & Children', pct: 70 },
  ]
// CMS-first: the Profile Stats / Profile Skills collections feed every
// leadership profile page; the authored rows seed them.
export const useMemberExtras = (): MemberProfileExtras => {
  const { items } = useCms()
  const stats = items('profileStats', []) as any[]
  const skills = items('profileSkills', []) as any[]
  return {
    stats: (stats.length || useCms().isSite) ? stats as MemberProfileExtras['stats'] : profileStats,
    skills: (skills.length || useCms().isSite) ? skills as MemberProfileExtras['skills'] : profileSkills,
  }
}

/** @olux-collection Leadership
 * @olux-field img image label="Photo"
 * @olux-field title text label="Title (e.g. Rev., Pastor, Deaconess)"
 * @olux-field name text required
 * @olux-field slug slug from=name label="Page address"
 * @olux-field role text
 * @olux-field bio textarea15
 * @olux-field about textarea15 label="About (profile page)"
 * @olux-field verse textarea2 label="Favourite verse"
 * @olux-field email email
 * @olux-field fact text label="Fun fact"
 * @olux-field ministries tags label="Ministries led"
 * @olux-field stats rows label="Stats (profile page)"
 * @olux-field stats.value text label="Number (e.g. 15+)"
 * @olux-field stats.label text label="Label (e.g. Years Serving)"
 * @olux-field focus rows label="Ministry focus (profile page)"
 * @olux-field focus.icon text label="Icon (an emoji)"
 * @olux-field focus.name text label="Focus area"
 * @olux-field socials rows label="Social accounts"
 * @olux-field socials.key select from=socials:key:name label="Platform"
 * @olux-field socials.href url label="Link"
 */
const members: Member[] = [
  {
    slug: 'daniel-okafor', focus: [{ icon: '📖', name: 'Teaching & Preaching' }, { icon: '🙏', name: 'Pastoral Care' }, { icon: '🤝', name: 'Community Outreach' }, { icon: '👥', name: 'Leadership Development' }], stats: [{ value: '15+', label: 'Years Serving' }, { value: '6', label: 'Ministries Led' }, { value: '1k+', label: 'Lives Touched' }], ministries: ["Men's Ministry"], img: '/assets/images/pastor-1.jpg', name: 'Rev. Daniel Okafor', role: 'Senior Pastor',
    bio: 'Pastor Daniel has led CAC Blackburn for over fifteen years. A gifted teacher with a shepherd\'s heart, he is passionate about seeing every believer rooted in scripture and serving the city. He is married to Adaeze and they have three children.',
    verse: '"Let all that you do be done in love." — 1 Corinthians 16:14', email: 'daniel@cacblackburn.org', fact: 'Preaches better after two coffees. Scientifically proven.',
    socials: [
      { key: 'youtube', href: 'https://youtube.com/@pastordaniel' },
      { key: 'facebook', href: 'https://facebook.com/pastordanielokafor' },
      { key: 'instagram', href: 'https://instagram.com/pastordaniel' },
      { key: 'x', href: 'https://x.com/pastordaniel' },
    ],
  },
  {
    slug: 'grace-lindqvist', focus: [{ icon: '🎵', name: 'Worship Leading' }, { icon: '🎤', name: 'Choir Direction' }, { icon: '🎹', name: 'Music Arranging' }, { icon: '🎧', name: 'Sound & Production' }], stats: [{ value: '12', label: 'Years Leading Worship' }, { value: '60+', label: 'Choir & Band' }, { value: '300+', label: 'Songs Taught' }], ministries: ["Women's Ministry"], img: '/assets/images/pastor-2.jpg', name: 'Grace Lindqvist', role: 'Worship & Music Director',
    bio: 'Grace directs our choirs, bands and production teams. She believes worship is for every voice — trained or not — and has built a music ministry where all skill levels find a place to serve.',
    verse: '"Sing to the Lord a new song." — Psalm 96:1', email: 'grace@cacblackburn.org', fact: 'Can harmonize with a fire alarm.',
    socials: [
      { key: 'youtube', href: 'https://youtube.com/@graceworship' },
      { key: 'instagram', href: 'https://instagram.com/graceworship' },
    ],
  },
  {
    slug: 'samuel-reyes', focus: [{ icon: '🧒', name: 'Youth Mentoring' }, { icon: '⛺', name: 'Camps & Retreats' }, { icon: '🤝', name: 'Community Outreach' }, { icon: '📖', name: 'Bible Teaching' }], stats: [{ value: '8', label: 'Years in Youth Work' }, { value: '120+', label: 'Young People' }, { value: '25', label: 'Camps Run' }], ministries: [], img: '/assets/images/pastor-3.jpg', name: 'Samuel Reyes', role: 'Youth & Outreach Pastor',
    bio: 'Samuel leads our teens and our neighbourhood outreach. From Friday youth nights to Saturday food drives, he lives the belief that faith gets real when it crosses the street.',
    verse: '"Let no one despise you for your youth." — 1 Timothy 4:12', email: 'samuel@cacblackburn.org', fact: 'Undefeated at table tennis. Allegedly.',
    socials: [
      { key: 'tiktok', href: 'https://tiktok.com/@pastorsam' },
    ],
  },
  {
    slug: 'esther-mwangi', focus: [{ icon: '🙏', name: 'Intercession' }, { icon: '🕯', name: 'Prayer Ministry' }, { icon: '💬', name: 'Counselling' }, { icon: '📖', name: 'Bible Study' }], stats: [{ value: '20', label: 'Years in Prayer' }, { value: '40+', label: 'Intercessors' }, { value: '5k+', label: 'Requests Prayed' }], ministries: ["Women's Ministry"], img: '/assets/images/pastor-4.jpg', name: 'Esther Mwangi', role: 'Prayer Ministry Lead',
    bio: 'Esther leads the intercessors who pray for our church, our city and every request left in the prayer box. She hosts the Wednesday early morning watch in the chapel.',
    verse: '"Pray without ceasing." — 1 Thessalonians 5:17', email: 'esther@cacblackburn.org', fact: 'Up praying before your alarm has even thought about it.',
  },
  {
    slug: 'ruth-alonso', focus: [{ icon: '🧒', name: 'Children\'s Ministry' }, { icon: '🎨', name: 'Creative Teaching' }, { icon: '👪', name: 'Family Support' }, { icon: '🛡', name: 'Safeguarding' }], stats: [{ value: '10', label: 'Years Serving' }, { value: '150+', label: 'Children Weekly' }, { value: '30', label: 'Volunteers' }], ministries: ["Women's Ministry"], img: '/assets/images/pastor-5.jpg', name: 'Ruth Alonso', role: 'Children\'s Ministry Director',
    bio: 'Ruth oversees everything from the nursery to grade six — a safe, joyful world where kids discover faith through stories, songs and play while their parents worship.',
    verse: '"Let the little children come to me." — Matthew 19:14', email: 'ruth@cacblackburn.org', fact: 'Brings the good snacks. Every time.',
  },
  {
    slug: 'peter-adeyemi', focus: [{ icon: '🌍', name: 'Missions' }, { icon: '🤝', name: 'Community Projects' }, { icon: '🍲', name: 'Food Pantry' }, { icon: '📋', name: 'Volunteer Teams' }], stats: [{ value: '14', label: 'Years Serving' }, { value: '9', label: 'Mission Partners' }, { value: '2k+', label: 'Meals Served' }], ministries: ["Men's Ministry"], img: '/assets/images/pastor-6.jpg', name: 'Peter Adeyemi', role: 'Missions & Community Pastor',
    bio: 'Peter coordinates our mission partnerships abroad and our community projects at home, from the food pantry to shelter support. He is happiest with his sleeves rolled up.',
    verse: '"Go into all the world." — Mark 16:15', email: 'peter@cacblackburn.org', fact: 'Owns more hi-vis vests than shirts.',
    socials: [
      { key: 'facebook', href: 'https://facebook.com/peteradeyemi' },
      { key: 'instagram', href: 'https://instagram.com/peteronmission' },
      { key: 'x', href: 'https://x.com/peteronmission' },
    ],
  },
]

// CMS-first: the site's "Leadership" collection feeds every profile;
// authored rows keep the pristine template identical and seed new sites.
export const useMembers = (): Member[] => {
  const { items } = useCms()
  const rows = (items('leadership', []) as any[]).filter(m => m.name && m.slug)
  const list = (rows.length || useCms().isSite) ? rows.map(m => ({ ...members.find(a => a.slug === m.slug), ...m }) as Member) : members
  return list.map(m => ({ ...m, name: withTitle(m) }))
}

/** "Pastor" + "Michael Adebayo" → "Pastor Michael Adebayo" (a name already starting with its title is left as it is). */
const withTitle = (m: Member) => {
  const t = String(m.title ?? '').trim()
  return t && !m.name.toLowerCase().startsWith(t.toLowerCase()) ? `${t} ${m.name}` : m.name
}
