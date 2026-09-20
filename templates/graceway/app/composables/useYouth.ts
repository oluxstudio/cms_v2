export interface YouthProgram {
  slug: string
  title: string
  tagline: string
  text: string
  meets: string
  place: string
  img: string
  tint: string
}

// Single source of truth for youth ministry programs.
const programs: YouthProgram[] = [
  {
    slug: 'youth-night', title: 'Youth Night', tagline: 'Fridays 6:30 PM',
    text: 'Games, pizza, honest conversation and worship — the week\'s highlight for teens across Blackburn.',
    meets: 'Fridays, 6:30 – 9:00 PM', place: 'Youth Hall', img: '/assets/images/circle-3.jpg', tint: 'yt-orange',
  },
  {
    slug: 'youth-bible-study', title: 'Bible Study', tagline: 'Big questions welcome',
    text: 'Teens dig into scripture together — honest answers, snacks, and space to say what they really think.',
    meets: 'Fridays, 7:15 PM (during Youth Night)', place: 'Youth Hall, Room 2', img: '/assets/images/event-1.jpg', tint: 'yt-purple',
  },
  {
    slug: 'youth-worship-team', title: 'Worship Team', tagline: 'Sing · play · create',
    text: 'The youth band and media crew that lead worship one Sunday a month. All skill levels — we train you.',
    meets: 'Rehearsals Thursdays, 6:00 PM', place: 'Main Sanctuary', img: '/assets/images/event-3.jpg', tint: 'yt-green',
  },
]

export const useYouth = () => programs
