# CLAUDE.md — Olux CMS client site

This is a **Nuxt 4 client site connected to an Olux CMS**. Content (text, images,
collection items, forms, booking) lives in the CMS; this repo owns layout and
markup. This file teaches you every way to interact with the CMS.

## Connection & env (`.env`, shell wins)

| Var | Purpose |
|---|---|
| `OLUX_CMS` | CMS base URL (local `http://localhost:8000`, or the live CMS) |
| `OLUX_SITE` | Site name in the CMS (e.g. `hairco`) |
| `OLUX_API_KEY` | **Management key** — server-side/CLI/MCP only, NEVER in browser code |

That's the whole .env. The public connect token for the connect.js script tag
is fetched from the CMS (`GET /api/site/connect-token`, authenticated with
`OLUX_API_KEY`) at build/dev-server start — see `resolveSiteToken()` in
`nuxt.config.ts`. Setting `OLUX_SITE_TOKEN` manually overrides the fetch
(useful when building without CMS access).

Switching local ↔ production CMS = changing these values (restart `nuxt dev` /
rebuild; the script tag is baked at build time from `nuxt.config.ts`).

## The marker contract (how markup binds to CMS content)

- `data-olx-key="hero" data-olx-kind="component|collection|form|post"` marks a
  block. Component key = **camelCase of the CMS component name** ("Book CTA" ↔
  `bookCta`); collection key = its slug; form key = its machine name.
- `data-olx-field="heading"` marks where a value renders (camelCase of the node
  label; dotted `cta.label` for nested). `<img>` fields get `src`, `<a>` gets
  `href` for URL-ish values, everything else gets text.
- Collections: exactly one `data-olx-item` element = the item template, cloned
  per published item.
- connect.js (installed via `nuxt.config.ts` head script) hydrates these
  markers from the published page.json and powers the CMS's visual edit preview.
- Components also read values through `useCms()` (`field('hero','heading','fallback')`) —
  keep the markup fallback text in sync with the field() fallback.

## Ways to interact with the CMS (pick by task)

1. **MCP tools (you have them — server `olux-cms` in `.mcp.json`)** — direct
   CRUD from the assistant: `load_page`, `get_field`, `list/create/update/delete`
   for components, collections+items, posts, forms, pages, services, plus
   `set_booking_availability` and `publish`. **Run `publish` after content
   mutations** so page.json (what this site renders) updates. Tool descriptions
   document payload shapes and required key abilities.
2. **`lib/olux-cms.mjs` SDK** — importable from app code and node scripts.
   Read layer (browser-safe, no key): `cms.page(path)` accessors, `form(name)
   .schema()/.submit()`, `booking.config()/availability()/book()`, `posts`.
   Manage layer (key required, server-side only): same CRUD as the MCP tools.
   `components.create({name, fields:{heading:'Hi', cta:{label:'Go', href:'/x'}}})`
   — the fields shorthand builds typed/nested nodes automatically.
3. **`scripts/cms-sync.mjs`** — STRUCTURE from markup. After adding/changing
   `data-olx-*` markers, run `node scripts/cms-sync.mjs --dry-run` then for
   real: creates missing components/collections/forms/pages, appends missing
   nodes/schema fields. Never overwrites values, never deletes.
4. **`scripts/cms-seed.mjs` + `scripts/cms-content.json`** — CONTENT manifest
   (collection items, posts, booking services/availability). Manifest is source
   of truth; `--fill-missing` to only add.
5. **`scripts/pull-cms.mjs`** — refresh the static `/public/cms.json` snapshot
   that `useCms()` uses as its offline fallback.

## Rules

- Management keys never appear in Vue components, nuxt.config public runtime
  config, or anything shipped to the browser. Only the connect token (the
  auto-fetched `data-site-token`, read+ingest abilities only) may be public.
- After creating a component in the CMS, it only renders here if (a) markup
  with the matching `data-olx-key`/fields exists AND (b) it's attached to the
  page in the CMS (use `page_ids` / `list_pages`).
- Don't delete CMS content to "clean up" unless asked — editors own content.
  The CMS keeps per-model history (last 5 versions) but be conservative.
- Booking/contact blocks (`AppointmentBlock`, `ContactBlock`) call the CMS
  booking/form APIs at runtime — the bookable services live in the CMS
  (`list_services`), not in this repo.
- The CMS edit preview lives at `{OLUX_CMS}/{site}/connect` — content edits
  made there republish automatically; this dev site picks them up live.
