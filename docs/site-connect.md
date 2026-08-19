# Site Connect

Site Connect ingests an existing client website into the CMS as structured
content, lets the owner edit it visually, and converts the client site to render
from a per-page JSON file the CMS publishes.

This doc tracks the build **stage by stage**. **Stage 1 (the contract) is
implemented**; later stages are specified in the build prompt and will be
appended as they land.

---

## The pipeline (whole feature)

1. Client adds one `<script src=".../connect.js" data-site-name data-site-token>`.
2. **Collect** — the script serialises the page; the CMS crawls + extracts.
3. **Classify** — sections become `component` / `collection` / `post` / `form`.
4. **Preview & edit** — the CMS renders a faithful replica; hover→edit through
   the existing mutation API.
5. **Publish** — the CMS generates `page.json` per page; the client site
   hydrates from it.
6. **GraphQL** — the same content model, query-level access.

### How it maps onto this codebase

The build prompt assumes a single `blocks` table. This codebase instead has
first-class **`Component`** (+ `Node` tree), **`Collection`** (+
`CollectionItem`), **`Post`**, and **`Form`** models composed onto **`Page`** via
the ordered `page_component` / `page_collection` pivots. Site Connect's four
classification targets map **1:1 onto those existing models** — no parallel
`blocks` table is introduced. `page.json` is therefore a reshaped, versioned
sibling of the existing `content.json` (`SiteContentController` / `SiteAppExporter`).

---

## Stage 1 — Data model + `page.json` generator (implemented)

### Data model

- **`site_connections`** (one per site): `mode` (`collect` | `hydrate`),
  `allowed_origins` (CORS/SSRF allow-list), `last_ingested_at`,
  `last_published_at`. Model: `App\Models\SiteConnection`, `Site::connection()`.
  The **token is not stored here** — Site Connect reuses the existing hashed,
  ability-scoped `api_tokens` (`connect:ingest`, `content:read` only).
- **`pages`** gains `page_json_version` (monotonic, the cache-bust key),
  `page_json_generated_at`, `page_json_path`.

### Services

- **`PageJsonGenerator::generate(Page, ?version)`** — builds the contract
  document by reusing the existing serializers (`Component::payload` node tree,
  `Collection` items, `Post`, `Form::toApiArray`). Pure read, no writes.
- **`PageJsonPublisher::publish(Page)`** — bumps the version, writes the JSON to
  the configured disk under a tenant-prefixed path, records
  path/version/time on the page, flips the connection to `hydrate`, purges CDN
  (logged no-op until the R2 wiring lands).

### Config — `config/site_connect.php`

| Key | Default | Meaning |
| --- | --- | --- |
| `schema_version` | `1` | Baked into every doc as `schemaVersion`; bump only on a breaking change. |
| `disk` | `public` (`SITE_CONNECT_DISK`) | Local in dev; set `s3` (R2-compatible) in prod. |
| `path_template` | `tenants/{site}/pages/{slug}.json` | Tenant-prefixed by construction. |
| `crawl.*` | per-tier caps | Used by Stage 3. |

### Endpoints & commands

- `GET /api/v1/sites/{siteName}/pages/{slug}.json` — public, read-only, throttled.
  Serves the published artefact; regenerates live as a fallback. Tenant-scoped:
  a slug only resolves within its own site.
- `php artisan connect:publish {site} [--page=/about]` — Stage 1 write path
  (the CMS Publish button will call `PageJsonPublisher` in Stage 4).

### v1 limitations (removed in later stages)

- **Forms / posts are site-level**, not page-scoped (no page↔form / page↔post
  association exists yet), so every page's JSON lists the site's active forms and
  published posts. Stage 3 adds page-level association.
- **Cross-type `position`** is a deterministic sequence (components → collections
  → forms → posts). Stage 4's builder lets users freely interleave.
- **`page.styles`** / `meta.ogImage` come from page attributes
  (`page_styles`, `og_image`) — empty until ingestion (Stage 3) populates them.
- **CDN purge** is a logged no-op until the R2 disk is wired.

---

## Stage 2 — `connect.js` hydrate mode + fixture (implemented)

### The connector — `resources/site-connect/connect.js`

Vanilla, dependency-free, **8.7 KB** (< 15 KB budget). Served from the CMS domain
at **`GET /connect.js`** (public, `Cache-Control: public, max-age=3600` for
Cloudflare). It:

1. Reads `data-site-name` + `data-site-token` from its own `<script>` tag and
   derives the CMS origin from its own `src`.
2. Calls **`GET /api/v1/connect/status?path=<pathname>`** with the token in the
   `Authorization: Bearer` header (never the URL) → `{mode, schemaVersion, pageJsonUrl}`.
3. In **hydrate** mode: fetches the `page.json`, checks `schemaVersion` and the
   baked `data-olx-version` (skips patching when the static HTML is already
   current — the SEO path), then:
   - fills `[data-olx-field]` nodes on each `[data-olx-id]` element (dotted paths
     like `cta.label`; images set `src`/`alt`; anchors set `href`);
   - rebuilds collections by cloning the `[data-olx-item]` template per entry;
   - rewires forms to `submitUrl` (fetch POST + inline success message);
   - applies theme CSS vars + injected `page.styles`.
4. **Fails silently** — every step is wrapped so a CMS outage or bad payload
   leaves the existing DOM untouched.

`collect` mode is a no-op until Stage 3.

### Endpoint — `GET /api/v1/connect/status`

Authenticated by the site's Bearer token (`auth.token` + `token.site` resolve the
site from the key). Returns the mode from `site_connections` and, in hydrate
mode, the absolute `page.json` URL for the requested path.

### Helper — `php artisan connect:token {site}`

Mints a hashed, **site-scoped** `api_token` limited to `connect:ingest` +
`content:read` (never mutation). Prints the raw token once + the ready-to-paste
`<script>` snippet.

### Manual test — the fixture

`public/site-connect-fixture/index.html` is a hand-written client page with
`data-olx-*` attributes. To see hydration:

```bash
php artisan connect:publish  <site>          # produce page.json (flips to hydrate)
php artisan connect:token    <site>          # get a token + snippet
```

Then edit the fixture's `REPLACE_WITH_*` placeholders (site name, token, and the
component/collection/form ids from the published JSON) and open
`http://localhost:8000/site-connect-fixture/index.html`. With `data-debug` on the
script tag, the console logs each step. Editing content in the CMS + republishing
(version bump) is reflected on reload.

---

## Stage 3 — Ingestion + classification (implemented)

The pipeline that turns a real external site into staged, classified content.

### Flow

1. **collect** — `connect.js` (collect mode) serialises the page (`documentElement.outerHTML`,
   same-origin CSS, meta, internal links) and POSTs it to
   **`POST /api/v1/connect/ingest`** (Bearer token, `connect:ingest` ability).
2. **stage** — `IngestController` stores a `PageIngestion` (received) and queues
   `IngestPageJob` + `CrawlSiteJob`.
3. **crawl** — `CrawlSiteJob` fetches the seed's internal links **server-side**,
   SSRF-guarded (same-host only, private-IP rejection, per-tier page cap; v1
   fetches HTML only, no JS execution) and queues each for extraction.
4. **extract + classify** — `IngestPageJob` → `IngestionProcessor`:
   sanitise (`HtmlSanitizer`) → split into sections (`PageExtractor`, dom-crawler)
   → classify each (`ContentClassifier`) → persist `IngestedSection` rows with a
   `confidence` and a `needs_review` flag (< 0.7). It also seeds the site theme
   from the CSS (`ThemeExtractor`), without clobbering existing edits.
5. **commit** — `IngestionProcessor::commit()` materialises confident sections
   into real **Component / Collection / Post / Form** via `SectionCommitter`,
   composed onto a resolved `Page`. `needs_review` sections wait for the Stage 4
   review queue.

### Classification heuristics (`ContentClassifier`)

| Kind | Signal | Confidence |
| --- | --- | --- |
| `form` | section contains a `<form>` | 0.97 + extracted fields + inferred intent (contact/newsletter/booking) |
| `collection` | a container with 3+ siblings sharing a tag+class signature | 0.55 + count + uniformity; extracts item schema + rows |
| `post` | `<article>`, or `/blog\|/news/` URL + byline | 0.7–0.98 |
| `component` | default (hero/about/footer) | 0.4–0.82 by signal count; named fields |

### Security (Part 7)

- **`HtmlSanitizer`** strips `<script>`/`<iframe>`/`<object>`, `on*` handlers,
  `javascript:` URLs; CSS loses `@import` + `expression()`. Applied on every
  store + preview path.
- **`SsrfGuard`** — crawl only the allow-listed host(s); reject non-http(s) and
  private/reserved IPs (defeats DNS-rebind).
- Ingest is token-gated (`connect:ingest`), tenant-scoped, throttled.

### Data model

- `page_ingestions` — raw snapshot + crawl metadata + status
  (received → extracting → classified → committed | failed).
- `ingested_sections` — per section: tag, sanitised html, `classification`,
  `confidence`, `needs_review`, extracted `fields`, and the `committed_ref_*`
  once materialised.

### v1 limitations

- **Images are not yet re-hosted** — extracted `src` values keep their original
  URLs (R2 re-upload lands with the media-rehost pass). Documented so the review
  queue can flag externally-hosted assets.
- **JS-rendered pages** the connector can't snapshot are skipped by the
  server-side crawl (no headless browser in v1).
- Auto-commit is opt-in per ingestion; low-confidence sections always go to review.

---

## Stage 4 — CMS preview + hover-to-edit (implemented)

Admin screen at **`/{site}/connect`** (sidebar: Content → Site Connect):

- **Faithful iframe replica** (`ConnectPreviewController@replica` +
  `site-connect/replica.blade.php`) — each section's sanitised HTML + the page's
  extracted CSS, wrapped with `data-olx-*` and a hover/click bridge (one-way
  postMessage of ids only, CSP-sandboxed).
- **Review inspector** (`ConnectReviewPage` Livewire) — hover→highlight,
  click→select; **reclassify** (component/collection/post/form, re-extracts
  fields), **commit** per section / **commit all**, **publish page.json**.
- **Inline editors** for committed content, all through the real models (no new
  write paths): component node values, collection **add/remove/edit items**, form
  fields **+ submit-endpoint override** (flows into `page.json` `submitUrl`), post
  title/excerpt/body.

## Stage 5 — Export / transform (implemented)

`SiteExporter` builds a downloadable **zip** of transformed pages
(`/{site}/connect/export`, `php artisan connect:export {site}`, or the "Download
export" button). Each page is:

- the section HTML annotated by `HtmlAnnotator` — `data-olx-id` on managed
  sections, `data-olx-field` on editable nodes, `data-olx-item` on repeating
  collection items;
- **content baked in** (correct for SEO on first paint);
- `data-olx-version` on `<html>` — connect.js only patches when a newer
  `page.json` version is published (static-for-SEO + live-without-redeploy);
- the `connect.js` hydrate `<script>` (token placeholder the owner pastes).

v1 annotation covers primary text/image fields + collection items + forms; nested
CTA objects stay baked (not yet live-editable).

## Stage 6 — GraphQL read API (implemented)

The project already runs **Lighthouse** at **`POST /api/graphql`** (read-only
content graph, `App\GraphQL\SiteGraph`, tenant-scoped, complexity ≤ 400, no
mutations). Site Connect adds **page.json-shaped queries** so GraphQL and the
REST `page.json` are one content model in two delivery formats:

```graphql
{
  site(name: "riverside-salon") {
    pageDocument(slug: "index") {
      schemaVersion
      title
      theme
      components  { id key position fields }
      collections { id key position schema items }
      posts       { id slug title excerpt publishedAt }
      forms       { id key position submitUrl fields successMessage }
    }
    collection(key: "services") { name items { id data } }
  }
}
```

`pageDocument` reuses `PageJsonGenerator`, so its shape can never drift from the
REST contract. Ideal for headless clients (e.g. a Nuxt site) that want typed
queries instead of the flat file. Writes remain on the REST mutation API.

## The `page.json` contract (schemaVersion 2)

```jsonc
{
  "schemaVersion": 2,
  "siteData": {
    "name": "riverside-salon", "domain": "riversidesalon.co.uk",
    "logo": "https://…/logo.svg", "icon": "https://…/favicon.png",
    "description": "…",
    "theme": { "fonts": {…}, "colors": {…}, "spacing": {…}, "radius": "12px", "containerMax": "1200px" },
    "nav": [{ "label": "Home", "href": "/" }],
    "currency": "GBP", "version": 4, "generatedAt": "2026-08-15T10:00:00Z"
  },
  "pageData": {
    "name": "Home", "url": "/", "slug": "index",
    "createdAt": "2026-08-01T…",
    "meta": { "description": "…", "ogImage": "https://…", "keywords": "…" },
    "styles": "/* extracted + edited page CSS */"
  },
  "componentData":  [ { "id","key","position","fields":{…} } ],
  "collectionData": [ { "id","key","position","schema":[…],"items":[…] } ],
  "formData":       [ { "id","key","position","submitUrl","fields":[…],"successMessage" } ],
  "bookingData":    { "services": [ { "slug","name","kind","price","duration","deposit","resources","formFields" } ],
                      "availability": { "weekdays":[…], "slotMinutes":30, "horizonDays":60 } },
  "postData":       [ { "id","slug","title","excerpt","body","publishedAt","featuredImage" } ]
}
```

- `logo`/`icon` come from site attributes (`getAttr('logo')` / `favicon`|`icon`).
- `bookingData` is the public booking **config** (bookable services + availability),
  empty `{services:[], availability:null}` when the site has no bookings feature —
  it never exposes customer bookings (PII).
- IDs are ULIDs, stable across publishes; `position` orders across content types;
  `version` cache-busts and gates the SEO baked-HTML patching.

### v1 → v2 notes
`schemaVersion` bumped 1 → 2: top-level `site`/`page`/`contents.*` became
`siteData`/`pageData`/`componentData`/`collectionData`/`formData`/`postData`, and
`bookingData` was added. `connect.js` checks `schemaVersion`, so HTML baked at v1
won't mis-hydrate against a v2 document.

## Legacy shape (schemaVersion 1 — superseded)

One document per published page — the single source of truth a client site
renders from. **Never break this shape without bumping `schemaVersion`**; the
hydration script checks compatibility before touching the DOM.

```json
{
  "schemaVersion": 1,
  "site": {
    "name": "riverside-salon",
    "domain": "riversidesalon.co.uk",
    "theme": {
      "fonts": { "heading": "Playfair Display", "body": "Inter" },
      "colors": { "primary": "#7c3aed", "surface": "#ffffff", "text": "#1f2937", "muted": "#6b7280" },
      "spacing": { "base": "1rem", "sectionY": "4rem" },
      "radius": "12px",
      "containerMax": "1200px"
    },
    "nav": [{ "label": "Home", "href": "/" }],
    "generatedAt": "2026-08-13T10:00:00Z",
    "version": 4
  },
  "page": {
    "slug": "index",
    "title": "Riverside Salon — Hair & Beauty",
    "meta": { "description": "...", "ogImage": "https://...", "keywords": "..." },
    "styles": "/* extracted + edited page CSS */"
  },
  "contents": {
    "components": [
      { "id": "01J...", "key": "hero", "position": 0,
        "fields": { "heading": "...", "image": { "src": "...", "alt": "..." },
                    "cta": { "label": "Book now", "href": "/book" } } }
    ],
    "collections": [
      { "id": "01J...", "key": "services", "position": 1,
        "schema": ["title", "price"],
        "items": [{ "id": "01J...", "title": "Cut & Finish", "price": "£38" }] }
    ],
    "posts": [
      { "id": "01J...", "slug": "summer-hair-care", "title": "...", "excerpt": "...",
        "body": "<sanitised html>", "publishedAt": "2026-07-01",
        "featuredImage": { "src": "...", "alt": "..." } }
    ],
    "forms": [
      { "id": "01J...", "key": "contact", "position": 2,
        "submitUrl": "https://cms.oluxstudio.com/api/sites/{site}/form/{name}",
        "fields": [{ "name": "email", "type": "email", "label": "Email", "required": true, "options": null }],
        "successMessage": "Thanks — we’ll be in touch." }
    ]
  }
}
```

Rules:

- **IDs are ULIDs, stable across publishes** — edits keep IDs; only new content
  gets new ones.
- **`position`** orders sections across all four content types.
- **`version`** increments per publish; the JSON is cache-busted by it and purged
  from the CDN on republish.
- **`submitUrl`** points at the existing throttled + honeypot + origin-checked
  submission endpoint — no new write path.

---

## Client type: static HTML vs. a framework (Nuxt/Next)

`connect.js` has two jobs, and only one of them suits a reactive framework:

| | Static / server-rendered HTML | Nuxt / Next / Vue / React SPA |
| --- | --- | --- |
| **collect** (ingest) | ✅ script snapshots the DOM | ✅ works — the script waits for hydration (`whenSettled`) before snapshotting |
| **hydrate** (render from page.json) | ✅ use `connect.js` DOM-patching | ❌ **don't** — the framework re-renders and overwrites patches / warns on hydration mismatch. Consume `page.json` (or GraphQL) headlessly instead. |

### Ingesting a Nuxt site (collect)

Add the connector once in `nuxt.config.ts` (it runs on every route):

```ts
export default defineNuxtConfig({
  app: {
    head: {
      script: [{
        src: 'http://localhost:8000/connect.js',
        'data-site-name': 'your-site-slug',
        'data-site-token': 'olx_live_xxx',
        'data-debug': '',        // logs each step to the console
        defer: true,
      }],
    },
  },
})
```

Caveat — the **server-side crawl** (`CrawlSiteJob`) fetches internal links with a
plain HTTP GET (no JS). It sees full content only if the Nuxt app is **SSR or
statically generated** (`nuxt generate`). For a pure SPA (`ssr: false`), rely on
the client-side collect snapshot per page instead — the crawl will see an empty
shell.

### Rendering a Nuxt site from page.json (headless — the right hydrate path)

Instead of `connect.js` hydrate, fetch the published JSON and bind it:

```ts
// composables/useOluxPage.ts
export function useOluxPage(slug = 'index') {
  const base = 'http://localhost:8000'
  const site = 'your-site-slug'
  return useFetch(`${base}/api/v1/sites/${site}/pages/${slug}.json`, {
    key: `olx-${slug}`,
  })
}
```

```vue
<!-- pages/index.vue -->
<script setup>
const { data: page } = await useOluxPage('index')
const hero = computed(() => page.value?.contents.components.find(c => c.key === 'hero'))
const services = computed(() => page.value?.contents.collections.find(c => c.key === 'services'))
</script>

<template>
  <section v-if="hero">
    <h1>{{ hero.fields.heading }}</h1>
    <img :src="hero.fields.image?.src" :alt="hero.fields.image?.alt">
    <a :href="hero.fields.cta?.href">{{ hero.fields.cta?.label }}</a>
  </section>

  <div v-if="services" class="grid">
    <article v-for="item in services.items" :key="item.id">
      <h3>{{ item.title }}</h3><p>{{ item.price }}</p>
    </article>
  </div>
</template>
```

Now every CMS edit + republish (version bump) is picked up on the next fetch —
no redeploy. Forms POST to the `submitUrl` in the JSON.

## Client installation snippet (target)

```html
<script
  src="https://cms.oluxstudio.com/connect.js"
  data-site-name="clients-site-slug"
  data-site-token="olx_live_xxxxxxxx"
  defer>
</script>
```

The token is sent as `Authorization: Bearer`, never in the URL. `connect.js`
(Stage 2) asks `GET /api/v1/connect/status` for its mode and, in `hydrate` mode,
fetches the `page.json` above.

## Syncing structure from client markup (cms-sync.mjs)

The client project's `data-olx-*` markers are the source of truth for structure.
After changing markup (new fields, new components/collections/forms):

```
node scripts/cms-sync.mjs --dry-run   # preview
node scripts/cms-sync.mjs             # apply + publish page.json
```

Env: `OLUX_CMS`, `OLUX_SITE`, `OLUX_API_KEY` (management key needing
components/collections/forms/pages manage + publish.manage for the final
publish). Structure-safe by default: missing components/nodes/schema fields/
form fields are created, existing CMS **values are never overwritten**
(`--overwrite` opts in); nothing is deleted. Posts and booking payloads are
content — manage those with `cms-seed.mjs` and its `cms-content.json` manifest.

## Client SDK & MCP

See [client-sdk.md](client-sdk.md) — the drop-in `lib/olux-cms.mjs` SDK (read +
manage layers), the `scripts/olux-mcp.mjs` MCP server for AI assistants working
in client projects, and the full CRUD cookbook.
