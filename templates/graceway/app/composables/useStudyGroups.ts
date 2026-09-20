export interface StudyGroup {
  slug: string
  icon: string
  title: string
  stat: string
  statLabel: string
  text: string
  meets: string
  place: string
  leader: string
  img: string
}
const groups: StudyGroup[] = [
  {
    slug: 'weekly-attendees', icon: '🌅', title: 'Weekly study attendees', stat: '40+', statLabel: 'Across all groups',
    text: 'Every week, more than forty people across Blackburn open the Bible together — before work, in homes, and in the youth hall. There is a seat for you in one of them.',
    meets: 'Groups run throughout the week', place: 'Church & homes across Blackburn', leader: 'Ruth & Peter Alonso', img: '/assets/images/circle-2.jpg',
  },
  {
    slug: 'home-groups', icon: '🏠', title: 'Home Groups', stat: '12+', statLabel: 'Groups across Blackburn',
    text: 'Weeknight small groups in homes across the city. Study scripture, share a meal, and belong to a circle small enough to know your name.',
    meets: 'Weeknights, various times', place: 'Homes across Blackburn', leader: 'Ruth & Peter Alonso', img: '/assets/images/welcome.jpg',
  },
  {
    slug: 'discipleship', icon: '🎓', title: 'Discipleship available', stat: '1-to-1', statLabel: 'Personal mentoring',
    text: 'Walk through faith with someone a few steps ahead. We pair you with a mature believer for one-to-one discipleship — scripture, prayer and honest conversation at your pace.',
    meets: 'Arranged around your schedule', place: 'Church or a local café', leader: 'Rev. Daniel Okafor', img: '/assets/images/event-1.jpg',
  },
  {
    slug: 'midweek-study', icon: '🔥', title: 'Midweek Study', stat: 'Wed 7 PM', statLabel: 'All-church Bible study',
    text: 'Our main weekly study for the whole church. Verse by verse through scripture together — teaching, discussion and prayer in the middle of the week.',
    meets: 'Wednesdays, 7:00 – 8:30 PM', place: 'Main Hall, CAC Blackburn', leader: 'Rev. Daniel Okafor', img: '/assets/images/circle-3.jpg',
  },
]

export const useStudyGroups = () => groups
