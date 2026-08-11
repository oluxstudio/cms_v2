# Talking to the CMS from your site — one key, no site name

Your site can create and manage its own content in the CMS with a single
credential: **`CMS_SITE_KEY`**. It's a per-site API key you generate in the CMS
(Site → API Keys). Because the key is bound to your site, you send it as a
Bearer header and the CMS resolves the site from it — you never put the site
name in the URL.

```
CMS_URL=https://cms.oluxstudio.com
CMS_SITE_KEY=•••••••   # from Site → API Keys (grant the abilities you need)
```

All endpoints live under **`/api/site/…`** and authenticate with:

```
Authorization: Bearer ${CMS_SITE_KEY}
```

## Confirm which site the key maps to

```bash
curl $CMS_URL/api/site -H "Authorization: Bearer $CMS_SITE_KEY"
# → { "site": "your-site", "abilities": ["components.manage", …], … }
```

## Create a component

```js
await fetch(`${CMS_URL}/api/site/components`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${CMS_SITE_KEY}`,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'Hero',
    nodes: [
      { label: 'title', type: 'text', value: 'Welcome' },
      { label: 'image', type: 'image', value: 'https://…/hero.jpg' },
    ],
    page_ids: [],          // optional: attach to pages
  }),
})
// → 201 { ok: true, component: { id, name, nodes, node_tree, … } }
```

## What you can do with the key

Reads (site from key): `GET /api/site/content`, `/page`, `/components`,
`/collections`, `/forms`, `/pages`, `/posts`, `/posts/{slug}/comments`, `/media`.

Writes (need the matching ability on the key): `components`, `collections`
(+`/{id}/items`), `forms`, `pages`, `posts`, `media` — each `POST` (create),
`PATCH /{id}` (update), `DELETE /{id}`. Comment moderation:
`GET /api/site/posts/{slug}/comments/moderate`, `PATCH/DELETE /api/site/comments/{id}`.

These mirror the `/api/sites/{siteName}/…` endpoints exactly — same request
bodies and responses — just without the site segment.

## Notes

- **Least privilege**: grant the key only the abilities it needs (e.g. just
  `components.manage` for a build script that syncs components).
- **Multi-site keys**: if a key is *not* bound to a single site (rare), add an
  `X-Olux-Site: <site-name>` header to pick the target; otherwise the CMS
  returns `409 Ambiguous site`.
- **Keep the key server-side** — use it from your build step or a server route,
  not from browser code, so it isn't exposed publicly.
