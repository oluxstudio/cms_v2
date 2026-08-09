# Prompt — convert a hardcoded Vue section into CMS-backed content (Olux CMS)

Copy everything in the fenced block below and give it to the AI agent working on
the **client website**. Fill in the four placeholders at the top first.

---

```
You are working in a Nuxt (Vue) client website that renders content from the Olux CMS.
Convert a HARDCODED section into CMS-managed content and make the site render it via
its existing SSG/SSR data flow, WITHOUT ever showing an empty section.

## Fill these in
- CMS_BASE:   https://cms.oluxstudio.com        (local dev: http://localhost:8000)
- SITE:       deve-site                          (the CMS site name)
- SECTION:    <e.g. About / Hero / Services>     (the Vue section to convert)
- COMPONENT:  <path, e.g. components/sections/about.vue and its components/about/* children>

## How the CMS models content — pick the right shape per field
1. SCALAR TEXT (headings, paragraphs, button labels) → **home-page attributes**.
   Editable in the CMS under the page's attributes; read on the client with attr('key').
   Name keys `{section}_{field}` (e.g. about_intro_headline, hero_sub).
2. REPEATING LISTS (stats, team, faq, cards, gallery/collage) → **Collections**.
   One collection per list, a field schema + items. Read on the client with
   collection('slug', fallback, mapFn). Media items store an absolute `url`
   (upload files in the CMS Assets library and use their URL).
3. A REUSABLE NAMED BLOCK attached to specific pages → a **Component** (a named bag
   of typed Nodes) via the components CRUD API, attached to pages by page_ids.
   Use this only when you truly need a reusable, page-attached unit; for most
   sections, attributes + collections (1 & 2) is simpler and is what this site
   already consumes.

## The client already has a consumption layer — REUSE IT
- server/api/site-data.get.ts aggregates the CMS public reads (collections + the
  home page with its attributes + posts) with a short in-memory TTL, returning
  null blocks on failure so the site never breaks when the CMS is down.
- composables/useCmsContent.ts exposes:
    attr(key, fallback)                        → ComputedRef<string>   (page attribute)
    collection(slug, fallback, (data,item)=>T) → ComputedRef<T[]>      (falls back when empty)
    posts                                       → ComputedRef<CmsPost[]>
- If these files do not exist, create them following this same shape before refactoring.

## CMS public endpoints (reads are unauthenticated)
- GET  {CMS_BASE}/api/sites/{SITE}/content            whole site: pages → components → nodes
- GET  {CMS_BASE}/api/sites/{SITE}/page?url=/          one page incl. its `attributes` map
- GET  {CMS_BASE}/api/sites/{SITE}/collections         public collections WITH their items
- POST {CMS_BASE}/api/graphql                           GraphQL read (pick exact fields)

## Steps
1. Read COMPONENT and list every piece of content: classify each as scalar text,
   a list, or media. Keep the current hardcoded values — they become the FALLBACKS.
2. Refactor the Vue: replace inline strings with attr('{section}_{field}', '<current value>')
   and inline arrays with collection('<slug>', <current array as fallback>, d => ({...})).
   For media/gallery items, map the collection item's `url` field to the component's
   src/image prop; keep bundled imports as the fallback array.
   Do NOT move behaviour (click handlers, animations, scroll logic) into the CMS —
   only content.
3. Golden rule: every attr()/collection() MUST pass the current hardcoded value as
   fallback, so the section renders identically when the CMS has no value yet or is
   unreachable. Never render an empty section.
4. Rendering mode — keep the section server-rendered (SSR) or built (SSG); useCmsContent
   uses useAsyncData so it resolves at SSR/build time. Do NOT fetch section content in
   the browser (no client-only fetch for page copy) — it hurts SEO and caching. Content
   changes appear within the site-data TTL (SSR) or on the next build (SSG).
5. Verify: `npm run build` compiles; run the site pointed at the CMS
   (NUXT_PUBLIC_CMS_BASE, NUXT_PUBLIC_CMS_SITE) and confirm the section renders from the
   CMS; then point at an unreachable CMS and confirm it falls back to the hardcoded copy
   with no error.

## What content to create in the CMS (report this back so it can be seeded)
Output the exact CMS content the section needs:
- attributes: a { key: value } map for the page (`/`)
- collections: for each, its slug, field schema, and item rows
- any media files that must be uploaded to the Assets library (with the item they map to)
This list is what gets created in the CMS (via the admin, the components/collections CRUD
API with a scoped token, or a seeder command) so the CMS and the client agree on keys/slugs.

## Deliverable
- The refactored COMPONENT files (content from CMS, hardcoded values retained as fallback).
- The CMS content manifest (attributes + collections + media) described above.
- Confirmation that `npm run build` passes and the CMS-off fallback works.
```

---

## Notes for you (the CMS operator), not part of the prompt

- **Attributes + collections vs Components**: this site's `useCmsContent()` reads page
  **attributes** and **collections** — so converted sections will NOT appear under the
  CMS "Components" page (that's the separate Components/nodes feature). That's expected;
  the About section lives as home-page attributes + the `stats`/`team`/`faq`/`collage`
  collections. If you specifically want a section to show as a reusable **Component**
  (nodes, attachable to pages), use the components CRUD API / the Components admin instead
  — say the word and the prompt can target that shape.
- To create the manifest the agent returns, either paste it into the CMS admin, or extend
  the `cms:seed-about`-style command per section, or POST it with a **site-scoped API
  token** (Settings → API Keys or the site's API keys panel).

---

# Prompt — create ONE CMS Component (nodes) and render a Vue block from it

Use this when you want a section stored as a reusable **Component** (typed nodes,
attached to a page) that shows on the CMS "Components" page — instead of page
attributes. Example target: `components/about/Intro.vue`.

```
You are working in a Nuxt (Vue) client website that renders content from the Olux CMS.
Create a CMS COMPONENT (a named bag of typed nodes) for a Vue block, attach it to the
home page, and refactor the Vue block to render from that component's nodes — with the
current hardcoded copy kept as fallback so the block never renders empty.

## Fill these in
- CMS_BASE:  https://cms.oluxstudio.com        (local dev: http://localhost:8000)
- SITE:      deve-site
- TOKEN:     <API token with components.manage on SITE — create it in the CMS under
             Settings → API Keys, or the site's API keys panel; send as Bearer>
- BLOCK:     components/about/Intro.vue
- COMPONENT_NAME: "About Intro"

## 1 · Create the component in the CMS (attached to the home page)
First get the home page id:
  GET {CMS_BASE}/api/sites/{SITE}/pages           → find the page whose url is "/", note its id

Then create the component with its nodes AND attach it in one call:
  POST {CMS_BASE}/api/sites/{SITE}/components
  Headers: Authorization: Bearer {TOKEN}   Content-Type: application/json
  Body:
  {
    "name": "About Intro",
    "description": "Intro block of the About section",
    "nodes": [
      { "label": "subtitle", "type": "text", "value": "From first sketch to final click, we help your vision come alive online." },
      { "label": "headline", "type": "text", "value": "Turning Bright Ideas into" },
      { "label": "accent",   "type": "text", "value": "Beautiful Websites" },
      { "label": "body",     "type": "text", "value": "A modern web studio is a creative and technical partner that designs and builds high-quality, custom digital experiences. We're a small, specialised team focused on creating websites and digital products that are visually striking, high-performing, and conversion-driven." },
      { "label": "cta",      "type": "text", "value": "See our services" }
    ],
    "page_ids": ["<home page id from the step above>"]
  }
Node `type` is one of: text, url, image, number, boolean, color, collection.
(Alternatively create it in the CMS admin: Components → New component → add these nodes
 → attach to the Home page. Same result.)

## 2 · Let the client read component nodes (it currently only reads attributes/collections)
The home page payload already includes its components:
  GET {CMS_BASE}/api/sites/{SITE}/page?url=/  →  page.components[] each with nodes[]{label,type,value}
The site aggregator (server/api/site-data.get.ts) returns the whole `page`, so
`data.page.components` is available. Add a helper to composables/useCmsContent.ts:

  /** A component's nodes as a label→value map, or {} when absent. */
  function componentNodes(name: string): ComputedRef<Record<string, string>> {
    return computed(() => {
      const c = (data.value?.page as any)?.components?.find((c: any) => c.name === name)
      return Object.fromEntries((c?.nodes ?? []).map((n: any) => [n.label, n.value ?? '']))
    })
  }
  // expose it in the return { ... , componentNodes }
If server/api/site-data.get.ts does not already surface page.components, ensure it
returns the full `page` object (it fetches /page?url=/ — keep the whole payload, not just attributes).

## 3 · Refactor the Vue block to render from the component (fallback preserved)
In BLOCK's <script setup>, replace the attr(...) lines with node reads that fall back
to the current hardcoded copy:

  const { componentNodes } = useCmsContent()
  const intro = componentNodes('About Intro')
  const introSubtitle = computed(() => intro.value.subtitle || 'From first sketch to final click, we help your vision come alive online.')
  const introHeadline  = computed(() => intro.value.headline || 'Turning Bright Ideas into')
  const introAccent    = computed(() => intro.value.accent   || 'Beautiful Websites')
  const introBody      = computed(() => intro.value.body     || "A modern web studio is a creative and technical partner that designs and builds high-quality, custom digital experiences. We're a small, specialised team focused on creating websites and digital products that are visually striking, high-performing, and conversion-driven.")
  const introCta       = computed(() => intro.value.cta      || 'See our services')

The template stays the same ({{ introHeadline }} etc.). Do NOT move behaviour into the CMS.

## 4 · Rendering & verify
- Keep the block SSR/SSG: useCmsContent uses useAsyncData, so nodes resolve at server/build
  time. Do not browser-fetch page content.
- `npm run build` must pass. Run with NUXT_PUBLIC_CMS_BASE + NUXT_PUBLIC_CMS_SITE pointed at the
  CMS: the block renders from the "About Intro" component; edits to its nodes in the CMS appear
  within the site-data TTL. Point at an unreachable CMS → it falls back to the hardcoded copy, no error.

## Deliverable
- The created component (id) attached to the home page, verifiable at
  {CMS_BASE}/api/sites/{SITE}/page?url=/ (page.components contains "About Intro" with its nodes)
  and on the CMS Components page.
- The componentNodes() helper + refactored BLOCK.
- `npm run build` passes and the CMS-off fallback works.
```
