// Per-page hero copy — AUTHORED here so the CMS page rewrite (which drops
// slots/props/inline markup) can never lose it. PageHeroContent + BreadCrumbs
// read this map by route; CMS field edits override at runtime.
export type HeroCopy = { eyebrow: string; title: string; text: string; crumbs: { label: string; to?: string }[] }

export const useHeroCopy = (): Record<string, HeroCopy> => ({
  "/privacy-policy": {
    "eyebrow": "Legal",
    "title": "Privacy Policy",
    "text": "How we collect, use and protect your personal information.",
    "crumbs": [
      {
        "label": "Privacy Policy"
      }
    ]
  },
  "/terms": {
    "eyebrow": "Legal",
    "title": "Terms & Conditions",
    "text": "The terms that govern your use of this website.",
    "crumbs": [
      {
        "label": "Terms & Conditions"
      }
    ]
  },
  "/cookie-policy": {
    "eyebrow": "Legal",
    "title": "Cookie Policy",
    "text": "How this website uses cookies and similar technologies.",
    "crumbs": [
      {
        "label": "Cookie Policy"
      }
    ]
  },
  "/donate": {
    "eyebrow": "Giving",
    "title": "\ud83d\udc9d Give generously, change lives",
    "text": "Your giving keeps our doors open, our pantry stocked, and our outreach on the streets.",
    "crumbs": [
      {
        "label": "Give"
      }
    ]
  },
  "/store": {
    "eyebrow": "Church Store",
    "title": "Books to grow your faith",
    "text": "Devotionals, study guides and stories of faith \u2014 hand-picked by our pastors to help you grow between Sundays.",
    "crumbs": [
      {
        "label": "Store"
      }
    ]
  },
  "/kids": {
    "eyebrow": "Ministries",
    "title": "\ud83e\uddd2 Kids Church",
    "text": "A safe, joyful world from nursery to grade six \u2014 stories, songs and play while parents worship.",
    "crumbs": [
      {
        "label": "Ministries",
        "to": "/ministries"
      },
      {
        "label": "Kids"
      }
    ]
  },
  "/worship": {
    "eyebrow": "Worship & praise",
    "title": "Every voice, lifted together",
    "text": "Gospel-rooted praise with a full choir and band \u2014 joyful, unhurried, and open to every generation.",
    "crumbs": [
      {
        "label": "Ministries",
        "to": "/ministries"
      },
      {
        "label": "Worship & Praise"
      }
    ]
  },
  "/newsletter": {
    "eyebrow": "Join the church",
    "title": "Become part of the family",
    "text": "Sign up for our newsletter and next-steps updates \u2014 we'll welcome you, keep you posted on services and events, and help you find your place at CAC Blackburn.",
    "crumbs": [
      {
        "label": "Join the Church"
      }
    ]
  },
  "/contact": {
    "eyebrow": "",
    "title": "Plan your visit",
    "text": "We can't wait to meet you. Reach out with any question \u2014 big or small.",
    "crumbs": [
      {
        "label": "Contact"
      }
    ]
  },
  "/broadcast": {
    "eyebrow": "Live broadcast",
    "title": "Worship with us, wherever you are",
    "text": "Every Sunday service streamed live on YouTube and Facebook \u2014 and soon on TikTok.",
    "crumbs": [
      {
        "label": "Events",
        "to": "/events"
      },
      {
        "label": "Live Broadcast"
      }
    ]
  },
  "/events": {
    "eyebrow": "",
    "title": "Events & gatherings",
    "text": "From worship nights to food drives \u2014 there's always something happening at CAC Blackburn.",
    "crumbs": [
      {
        "label": "Events"
      }
    ]
  },
  "/about": {
    "eyebrow": "",
    "title": "Our story & our faith",
    "text": "Thirty years of worship, friendship and service in the heart of Springfield.",
    "crumbs": [
      {
        "label": "About Us"
      }
    ]
  },
  "/prayer": {
    "eyebrow": "Ministries",
    "title": "\ud83d\udd6f Prayer Ministry",
    "text": "Intercessors praying for the church, the city and every request received \u2014 join the Wednesday 6 AM watch or pray from home.",
    "crumbs": [
      {
        "label": "Ministries",
        "to": "/ministries"
      },
      {
        "label": "Prayer"
      }
    ]
  },
  "/ministries": {
    "eyebrow": "",
    "title": "Our Ministries",
    "text": "Every gift matters and every hand is needed \u2014 find the place where you can serve, grow and belong.",
    "crumbs": [
      {
        "label": "Ministries"
      }
    ]
  },
  "/youth": {
    "eyebrow": "Youth ministry",
    "title": "Made for more",
    "text": "A community for ages 13\u201319 \u2014 Friday nights, honest Bible study, and a worship team of their own.",
    "crumbs": [
      {
        "label": "Ministries",
        "to": "/ministries"
      },
      {
        "label": "Youth"
      }
    ]
  },
  "/sermons": {
    "eyebrow": "Sermons",
    "title": "Every message, in one place",
    "text": "Watch, listen or read \u2014 catch up on any Sunday, from any series.",
    "crumbs": [
      {
        "label": "Sermons"
      }
    ]
  },
  "/community-care": {
    "eyebrow": "Community care & outreach",
    "title": "No one in Blackburn should face hardship alone",
    "text": "Practical love, every week \u2014 food, friendship, warmth and presence for our town and our partners abroad.",
    "crumbs": [
      {
        "label": "Ministries",
        "to": "/ministries"
      },
      {
        "label": "Community Care"
      }
    ]
  },
  "/bible-study": {
    "eyebrow": "Bible study",
    "title": "Go deeper in the Word",
    "text": "Find the study or small group that fits your week \u2014 every group welcomes first-timers.",
    "crumbs": [
      {
        "label": "Ministries",
        "to": "/ministries"
      },
      {
        "label": "Bible Study"
      }
    ]
  }
})
