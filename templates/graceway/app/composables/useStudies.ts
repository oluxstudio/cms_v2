import type { ContentMedia } from './useSermons'

export interface Study {
  slug: string
  title: string
  series: string
  week?: string
  passage: string
  excerpt: string
  summary: string
  questions: string[]
  takeaway: string
  memoryVerse: string
  audience: 'Everyone' | 'Youth' | 'Women' | 'Men' | 'Seniors'
  date: string
  /** who wrote / leads the study */
  author?: string
  /** the study's content (notes) */
  content?: string
  /** off = hidden from the site */
  published?: boolean
  /** media files from the CMS — mapped to videoUrl / audioUrl / poster */
  media?: ContentMedia[]
  videoUrl?: string
  /** audio recording — external or local file */
  audioUrl?: string
  /** poster shown by the MediaPlayer facade */
  poster?: string
}

// Single source of truth for all Bible studies — newest first.
/** @olux-collection Bible Studies
 * @olux-field slug text hidden
 * @olux-field title textarea2 required
 * @olux-field author text label="Author"
 * @olux-field summary textarea6 label="Description"
 * @olux-field content textarea15 label="Content"
 * @olux-field passage textarea
 * @olux-field excerpt textarea
 * @olux-field questions list
 * @olux-field takeaway textarea10
 * @olux-field memoryVerse textarea2 label="Memory verse"
 * @olux-field audience select options=Everyone|Youth|Women|Men|Seniors
 * @olux-field date datetime label="Publish date & time"
 * @olux-field published toggle label="Published"
 * @olux-field media rows label="Media"
 * @olux-field media.type select options=video|audio|image
 * @olux-field media.title text
 * @olux-field media.src media label="File from Assets (or a YouTube / TikTok / Facebook link)"
 * @olux-field media.img image label="Poster image" show=type:video|audio
 */
const studies: Study[] = [
  {
    slug: 'rooted-week-4-fruit-that-lasts', title: 'Fruit That Lasts', series: 'Rooted', week: 'Week 4 of 4',
    passage: 'John 15:1–17', excerpt: '"I am the vine; you are the branches. If you remain in me and I in you, you will bear much fruit." — John 15:5',
    summary: 'The series closes where Jesus closes: fruitfulness is not produced, it is grown — by staying connected. What "remaining" looks like in an ordinary week.',
    questions: ['What does "remaining" in Jesus practically mean for you?', 'Where are you trying to produce fruit instead of growing it?', 'Which branch in your life needs pruning?', 'Who has been a gardener in your faith?'],
    takeaway: 'Choose one daily connection point this week — same time, same place, ten minutes with the vine.',
    memoryVerse: 'John 15:5', audience: 'Everyone', date: '2026-09-10T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'video', title: '', src: 'https://www.w3schools.com/html/mov_bbb.mp4', img: '/assets/images/gallery-7.jpg' }, { type: 'audio', title: '', src: 'https://www.w3schools.com/html/horse.mp3', img: '/assets/images/bible-study.jpg' }],
  },
  {
    slug: 'identity-week-3-what-they-call-you', title: 'What They Call You', series: 'Identity', week: 'Week 3 of 5',
    passage: 'Ephesians 2:1–10', excerpt: '"For we are God\'s handiwork, created in Christ Jesus to do good works." — Ephesians 2:10',
    summary: 'Labels stick — from classmates, family, even ourselves. This study puts the loudest labels next to the names God actually uses.',
    questions: ['What label has stuck to you the longest?', 'Which name in this passage is hardest to believe about yourself?', 'How would this week change if "handiwork" was your default label?'],
    takeaway: 'Write God\'s name for you on your lock screen. Read it every time the phone lights up.',
    memoryVerse: 'Ephesians 2:10', audience: 'Youth', date: '2026-09-05T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'video', title: '', src: 'https://youtu.be/JvnJYySur1o?si=M2KnClRpfEELYgFl', img: '/assets/images/gallery-3.jpg' }],
  },
  {
    slug: 'rooted-week-3-dry-seasons', title: 'Dry Seasons', series: 'Rooted', week: 'Week 3 of 4',
    passage: 'Psalm 1:1–6', excerpt: '"That person is like a tree planted by streams of water… whose leaf does not wither." — Psalm 1:3',
    summary: 'Every believer meets a season where prayer feels dry and nothing seems to grow. Psalm 1 says the tree survives drought by where it is planted, not how it feels.',
    questions: ['How do you recognise a dry season in your own life?', 'What is the difference between feeling dry and being uprooted?', 'What are your "streams" — and are you actually planted by them?'],
    takeaway: 'Keep your rhythms this week even if they feel empty. Roots drink before leaves show it.',
    memoryVerse: 'Psalm 1:3', audience: 'Everyone', date: '2026-09-03T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [],
  },
  {
    slug: 'pressure-points-week-2-anxiety', title: 'Anxiety and the Anchor', series: 'Pressure Points', week: 'Week 2 of 4',
    passage: 'Philippians 4:4–9', excerpt: '"Do not be anxious about anything, but in every situation… present your requests to God." — Philippians 4:6',
    summary: 'Paul wrote "do not be anxious" from a prison cell. What he prescribes instead is not positive thinking but a practice — prayer with thanksgiving, and a guarded mind.',
    questions: ['What does anxiety feel like in your body — and what triggers it?', 'Is Paul dismissing anxiety or giving it somewhere to go?', 'What would "thinking about such things" (v8) look like on your feed?'],
    takeaway: 'Try the Philippians pattern once a day: name the worry, thank God for one thing, hand it over.',
    memoryVerse: 'Philippians 4:6–7', audience: 'Youth', date: '2026-08-29T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'video', title: '', src: 'https://www.tiktok.com/@scout2015/video/6718335390845095173', img: '/assets/images/gallery-11.jpg' }],
  },
  {
    slug: 'women-of-the-word-ruth-1', title: 'Where You Go, I Will Go', series: 'Women of the Word', week: 'Ruth · Part 1',
    passage: 'Ruth 1:1–22', excerpt: '"Where you go I will go, and where you stay I will stay." — Ruth 1:16',
    summary: 'Ruth opens with famine, funerals and a decision. A study on loyalty that costs something, and the God who works through ordinary faithfulness.',
    questions: ['What did Ruth actually give up in verse 16?', 'Where has loyalty cost you something?', 'How does Naomi\'s honesty about bitterness (v20) sit with you?'],
    takeaway: 'Reach out to someone walking through their own "famine" this week — presence over advice.',
    memoryVerse: 'Ruth 1:16', audience: 'Women', date: '2026-08-26T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'video', title: '', src: 'https://www.facebook.com/facebook/videos/10153231379946729/', img: '/assets/images/gallery-2.jpg' }],
  },
  {
    slug: 'men-at-dawn-nehemiah-1', title: 'Build With Your Sword On', series: 'Men at Dawn', week: 'Nehemiah · Part 1',
    passage: 'Nehemiah 1:1–11', excerpt: '"So we rebuilt the wall… for the people worked with all their heart." — Nehemiah 4:6',
    summary: 'Nehemiah heard bad news, wept, prayed — and then made a plan. A men\'s breakfast study on carrying responsibility without carrying it alone.',
    questions: ['Nehemiah prayed for months before acting. What are you rushing?', 'What "wall" in your family or workplace needs rebuilding?', 'Who are your fellow builders?'],
    takeaway: 'Name one broken thing you\'ve been ignoring and take a first concrete step this week.',
    memoryVerse: 'Nehemiah 1:11', audience: 'Men', date: '2026-08-21T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'video', title: '', src: 'https://fb.watch/xYs4mpl3vid/', img: '/assets/images/gallery-4.jpg' }],
  },
  {
    slug: 'rooted-week-2-planted-in-community', title: 'Planted in Community', series: 'Rooted', week: 'Week 2 of 4',
    passage: 'Acts 2:42–47', excerpt: '"They devoted themselves to the apostles\' teaching and to fellowship." — Acts 2:42',
    summary: 'Nobody grows alone. The first church devoted themselves to four things — and every one of them happened together.',
    questions: ['Which of the four devotions comes easiest to you? Hardest?', 'What is the difference between attending and belonging?', 'Who knows how your faith is actually doing?'],
    takeaway: 'Share one honest prayer request with another believer this week — not a safe one.',
    memoryVerse: 'Acts 2:42', audience: 'Everyone', date: '2026-08-20T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [],
  },
  {
    slug: 'golden-years-psalm-90', title: 'Teach Us to Number Our Days', series: 'Golden Years', week: 'Psalms · Part 3',
    passage: 'Psalm 90:1–17', excerpt: '"Teach us to number our days, that we may gain a heart of wisdom." — Psalm 90:12',
    summary: 'Moses\' only psalm looks back over a long life and asks God to make the remaining days count. A daytime study on legacy, wisdom and hope.',
    questions: ['What has a long walk with God taught you that youth could not?', 'What does "establish the work of our hands" mean at every age?', 'Who are you passing wisdom to?'],
    takeaway: 'Tell one younger person a story of God\'s faithfulness in your life this week.',
    memoryVerse: 'Psalm 90:12', audience: 'Seniors', date: '2026-08-14T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'video', title: '', src: 'https://www.facebook.com/watch/?v=1093831888126364', img: '/assets/images/gallery-8.jpg' }],
  },
  {
    slug: 'identity-week-2-comparison', title: 'The Comparison Trap', series: 'Identity', week: 'Week 2 of 5',
    passage: 'Galatians 6:1–5', excerpt: '"Each one should test their own actions… without comparing themselves to someone else." — Galatians 6:4',
    summary: 'Scrolling is a comparison machine. Paul offers another way to measure a life — and it isn\'t against anyone else\'s highlight reel.',
    questions: ['Where does comparison hit you hardest?', 'What is the difference between inspiration and comparison?', 'What load is actually yours to carry (v5)?'],
    takeaway: 'Mute one account that consistently makes you feel less. Replace it with the memory verse.',
    memoryVerse: 'Galatians 6:4', audience: 'Youth', date: '2026-08-08T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'video', title: '', src: 'https://www.tiktok.com/@tiktok/video/7106594312292453675', img: '/assets/images/circle-3.jpg' }],
  },
  {
    slug: 'rooted-week-1-good-soil', title: 'Good Soil', series: 'Rooted', week: 'Week 1 of 4',
    passage: 'Mark 4:1–20', excerpt: '"Still other seed fell on good soil. It came up, grew and produced a crop." — Mark 4:8',
    summary: 'The sower\'s seed never changes — the soil does. Series opener on what makes a heart receptive, and what quietly chokes growth.',
    questions: ['Which soil best describes this season of your life?', 'What are the "thorns" (v19) in your week?', 'What would breaking up hard ground look like for you?'],
    takeaway: 'Clear thirty minutes of "thorns" this week and give that time to the Word instead.',
    memoryVerse: 'Mark 4:20', audience: 'Everyone', date: '2026-08-06T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'video', title: '', src: 'https://www.w3schools.com/html/mov_bbb.mp4', img: '' }],
  },
  {
    slug: 'pressure-points-week-1-stress', title: 'Carrying It Alone', series: 'Pressure Points', week: 'Week 1 of 4',
    passage: 'Matthew 11:25–30', excerpt: '"Come to me, all you who are weary and burdened, and I will give you rest." — Matthew 11:28',
    summary: 'School, family, expectations — the weight adds up quietly. Jesus doesn\'t offer an escape from the load, but a different way to carry it.',
    questions: ['What weight are you carrying that nobody knows about?', 'What does "rest for your souls" actually mean?', 'What makes Jesus\' yoke "easy" — is it lighter, or shared?'],
    takeaway: 'Tell one trusted person about one burden this week. Carrying together is the design.',
    memoryVerse: 'Matthew 11:28', audience: 'Youth', date: '2026-07-30T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [],
  },
  {
    slug: 'women-of-the-word-proverbs-31', title: 'Strength and Dignity', series: 'Women of the Word', week: 'Standalone',
    passage: 'Proverbs 31:10–31', excerpt: '"She is clothed with strength and dignity; she can laugh at the days to come." — Proverbs 31:25',
    summary: 'Read poorly, this chapter is a to-do list. Read well, it is a portrait of strength. Reclaiming a passage that has been used to exhaust women.',
    questions: ['How has this passage been used around you — burden or blessing?', 'Which single verse here rings truest to your season?', 'What does "laughing at the days to come" require?'],
    takeaway: 'Encourage a woman whose quiet strength you have noticed — tell her specifically what you see.',
    memoryVerse: 'Proverbs 31:25', audience: 'Women', date: '2026-07-22T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [],
  },
  {
    slug: 'psalms-of-ascent-week-1-lift-my-eyes', title: 'I Lift My Eyes', series: 'Psalms of Ascent', week: 'Week 1 of 3',
    passage: 'Psalm 121:1–8', excerpt: '"I lift up my eyes to the mountains — where does my help come from?" — Psalm 121:1',
    summary: 'The traveller\'s psalm. Where we instinctively look when trouble comes, and how to retrain the reflex.',
    questions: ['Where do your eyes go first under pressure?', 'What does "he will not let your foot slip" NOT promise?', 'Who kept watch over you this year?'],
    takeaway: 'Memorise the first two verses and say them on every uphill walk this week.',
    memoryVerse: 'Psalm 121:1–2', audience: 'Everyone', date: '2026-09-10T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'video', title: '', src: '/assets/media/study-recap-1.mp4', img: '/assets/images/gallery-5.jpg' }],
  },
  {
    slug: 'psalms-of-ascent-week-2-peace-within', title: 'Peace Within These Walls', series: 'Psalms of Ascent', week: 'Week 2 of 3',
    passage: 'Psalm 122:1–9', excerpt: '"I rejoiced with those who said to me, let us go to the house of the Lord." — Psalm 122:1',
    summary: 'Gladness as a discipline. Why the pilgrims sang on the road, and what praying for the peace of your own city looks like.',
    questions: ['When did church last feel like rejoicing?', 'What would praying for Blackburn\'s peace change in you?', 'Who could you travel with, spiritually, this month?'],
    takeaway: 'Pray Psalm 122:6–9 over your street, naming your neighbours.',
    memoryVerse: 'Psalm 122:1', audience: 'Everyone', date: '2026-09-12T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'video', title: '', src: '/assets/media/study-recap-2.mp4', img: '/assets/images/gallery-9.jpg' }],
  },
  {
    slug: 'listening-week-1-still-small-voice', title: 'The Still Small Voice', series: 'Listening', week: 'Week 1 of 2',
    passage: '1 Kings 19:9–13', excerpt: '"After the fire came a gentle whisper." — 1 Kings 19:12',
    summary: 'Elijah expected the spectacular and got a whisper. An audio study on hearing God in the quiet — best with headphones.',
    questions: ['What noise drowns out the whisper for you?', 'Why do you think God chose the quiet?', 'Where is your Horeb — the place you actually listen?'],
    takeaway: 'Ten minutes of silence before the podcast, the playlist, the scroll — every morning this week.',
    memoryVerse: '1 Kings 19:12', audience: 'Everyone', date: '2026-09-14T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'audio', title: '', src: 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3', img: '/assets/images/gallery-10.jpg' }],
  },
  {
    slug: 'listening-week-2-speak-lord', title: 'Speak, Lord', series: 'Listening', week: 'Week 2 of 2',
    passage: '1 Samuel 3:1–10', excerpt: '"Speak, for your servant is listening." — 1 Samuel 3:10',
    summary: 'Samuel heard his name three times before he knew who was calling. An audio walk through learning to recognise the voice.',
    questions: ['Who is your Eli — the one who helps you interpret?', 'What has God repeated to you lately?', 'What would answering "speak, Lord" cost this week?'],
    takeaway: 'Tell one trusted person what you think God has been repeating, and ask what they hear.',
    memoryVerse: '1 Samuel 3:10', audience: 'Youth', date: '2026-09-16T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'audio', title: '', src: 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3', img: '' }],
  },
  {
    slug: 'selah-meditation-psalm-46', title: 'Be Still: A Guided Meditation', series: 'Selah', week: 'Standalone',
    passage: 'Psalm 46:1–11', excerpt: '"Be still, and know that I am God." — Psalm 46:10',
    summary: 'A short guided stillness exercise recorded at the chapel — breathe, read, and let the psalm slow you down.',
    questions: ['What does stillness feel like in your body?', 'Which line of the psalm found you today?', 'When could Selah fit your daily rhythm?'],
    takeaway: 'Return to this recording once a day for three days and journal one word each time.',
    memoryVerse: 'Psalm 46:10', audience: 'Everyone', date: '2026-09-18T19:00',
    author: 'Ruth Alonso', published: true, content: '',
    media: [{ type: 'audio', title: '', src: '/assets/media/study-audio-1.mp4', img: '/assets/images/gallery-12.jpg' }],
  },
]

// CMS-first: the "Bible Studies" collection feeds the archive; authored rows seed it.
export const useStudies = (): Study[] => {
  const rows = (useCms().items('bibleStudies', []) as any[]).filter(s => s.title)
  const list: any[] = (rows.length || useCms().isSite) ? rows.map(s => ({ ...studies.find(a => a.slug && a.slug === s.slug), ...s })) : studies
  return list.map((s) => {
    // media rows → the video / recording the MediaPlayer plays (any link it understands)
    const media: ContentMedia[] = (Array.isArray(s.media) ? s.media : []).filter((m: any) => m && m.src)
    const video = media.find(m => m.type === 'video')
    const audio = media.find(m => m.type === 'audio')
    return {
      ...s,
      slug: String(s.slug ?? '').trim() || String(s.title).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''),
      published: isOn(s.published),
      questions: Array.isArray(s.questions) ? s.questions : [],
      media,
      videoUrl: video?.src ?? s.videoUrl,
      audioUrl: audio?.src ?? s.audioUrl,
      poster: video?.img || audio?.img || s.poster,
    } as Study
  }).filter(isLive)
}
