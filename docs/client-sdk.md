# Client SDK & MCP — how a client site talks to the CMS

Everything a connected client site needs, from loading content to full CRUD.
The reference implementation lives in the hairco template: `lib/olux-cms.mjs`
(SDK), `scripts/olux-mcp.mjs` + `.mcp.json` (MCP server for AI assistants),
`CLAUDE.md` (agent instructions). Copy those four into any client project.

## Tokens

| Token | Prefix | Abilities | Lives | Used for |
|---|---|---|---|---|
| Connect token | `olx_live_` | `connect:ingest`, `content:read` | Public HTML (script tag) | connect.js hydrate/collect/edit |
| Management key | any | `*.manage` per model + `publish.manage` | `.env` / CI secret — never the browser | SDK manage layer, MCP, cms-seed/cms-sync |

Mint either in the CMS (Settings → API tokens; tick sites + abilities) or
`php artisan connect:token <site>` for the connect token.

## SDK quick start

```js
import { createCms } from './lib/olux-cms.mjs'
const cms = createCms({ url: process.env.OLUX_CMS, site: process.env.OLUX_SITE, key: process.env.OLUX_API_KEY })
```

### Loading content (no key)
```js
const page = await cms.page('/about')
page.field('hero', 'heading', 'fallback')   // dotted paths: field('hero','cta.label')
page.items('services')                      // collection items
page.component('hero') / page.collection('team') / page.form('contact') / page.raw
```
Equivalent endpoint: `GET /api/v1/sites/{site}/pages/{slug}.json` (slug: `/`→`index`, `/a/b`→`a-b`).

### CRUD (management key, server-side)
```js
// Components — fields shorthand builds typed + nested nodes:
const { component } = await cms.components.create({
  name: 'Promo Banner',                       // camelCase(name) = data-olx-key
  fields: { heading: 'Summer sale',
            image: { type: 'image', value: '/img/promo.jpg' },
            cta: { label: 'Shop', href: '/shop' } },   // → data-olx-field="cta.label"
  page_ids: [(await cms.pages.list()).pages[0].id],
})
await cms.components.update(component.id, { fields: { heading: 'Winter sale' } }) // replaces ALL nodes
await cms.components.remove(component.id)

// Collections + items
const { collection } = await cms.collections.create({ name: 'Team', slug: 'team', schema: ['name', 'role'] })
await cms.items(collection.id).add({ name: 'Ada', role: 'Stylist' })

// Posts / Forms / Pages / Booking
await cms.posts.create({ title: 'Hello', body: '<p>…</p>' })
await cms.forms.create({ name: 'quote', title: 'Get a quote',
  fields: [{ key: 'email', label: 'Email', type: 'email', required: true }] })
await cms.booking.services.create({ name: 'Haircut', duration_min: 45, price_cents: 3800 })
await cms.booking.settings({ days: 'mon,tue,wed,thu,fri', open_time: '09:00', close_time: '17:00', slot_minutes: 30 })

await cms.publish()   // ALWAYS after mutations — republishes page.json
```

### Forms & booking end-to-end (browser-safe)
```js
const { fields } = await cms.form('contact').schema()      // render these
await cms.form('contact').submit({ email: 'a@b.c', message: 'Hi' })  // → CRM responses

const { services } = await cms.booking.config()
const { openDates, slots } = await cms.booking.availability({ service: 'haircut', date: '2026-09-01' })
const res = await cms.booking.book({ service: 'haircut', start: slots[0].iso, name, email, phone })
// res.checkout_url → redirect to Stripe when payment is required; res.reference otherwise
```

## MCP (AI assistants inside the client project)

`.mcp.json` registers `node scripts/olux-mcp.mjs` — 28 tools mirroring the SDK
(`load_page`, `get_field`, `create_component`, `add_item`, `create_form`,
`create_service`, `publish`, …). Each tool's schema documents its payload and
required ability. The server runs locally over stdio and reads the same
`OLUX_*` env vars, so the management key never leaves the developer's machine.

## Which tool when

| Task | Use |
|---|---|
| Render CMS content in the app | `useCms()` / SDK read layer / connect.js hydration |
| New markup fields → CMS structure | `scripts/cms-sync.mjs` |
| Bulk content (items, posts, booking) | `scripts/cms-seed.mjs` + manifest |
| One-off CRUD from code or CI | SDK manage layer |
| AI assistant working in the repo | MCP tools |
| Human editing | CMS Connect preview (`/{site}/connect`) |

## Endpoint reference (SDK/MCP surface)

- Public: `GET /api/v1/sites/{s}/pages/{slug}.json` · `GET /api/sites/{s}/{components,collections,posts,forms}` · `GET/POST /api/sites/{s}/form/{name}` · `GET /api/sites/{s}/booking/{config,availability}` · `POST /api/sites/{s}/booking`
- Token-context (Bearer + optional `X-Olux-Site`): `POST/PATCH/DELETE /api/site/{components,collections,collections/{id}/items,posts,forms,pages}` · `GET/POST/PATCH/DELETE /api/site/services` · `PATCH /api/site/booking-settings` · `POST /api/site/connect/publish`
