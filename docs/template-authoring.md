# Authoring a template for the Olux CMS

Build a normal Nuxt 4 site, follow the contract below, push it to a git repo
(private works) — the CMS clones, extracts, lints and publishes it. Start from
`templates-starter/` in this repo.

## How templates get in
| Path | How |
|---|---|
| **Git repo (recommended)** | Submissions page → *Import from repository* (URL + optional branch), or `php artisan template:import <url> [--key=k] [--accept] [--build]`. Private https repos need `TEMPLATES_GIT_TOKEN`; SSH URLs use the server's deploy key. Configure a push webhook to `POST /hooks/template-repo` (secret `TEMPLATES_REPO_WEBHOOK_SECRET`, GitHub `X-Hub-Signature-256`) and every push re-imports — already-accepted templates republish automatically. |
| **Zip upload** | Submissions page → *Upload app .zip* (source only, ≤60 MB, no node_modules). |
| **Server folder** | Drop the app into the staging mount (`../templates/{key}`) and press *Scan staging folder*. |

After intake: **Scan → lint findings → Preview build → Accept** publishes the
app + its content package and adds it to the public gallery (`/designs`).

## Hard requirements (intake fails without these)
1. `package.json` at the root with a working `nuxt generate` (Nuxt 4).
2. `app/pages/` with at least one top-level `.vue`; `index.vue` is the home page.
3. `app/components/` **flat** — every block a page uses lives here. Block names
   end in `Block.vue`; site chrome is `SiteHeader.vue` / `SiteFooter.vue`.
4. `nuxt.config.ts` (or `.js`) at the root; `ssr: false`; no hardcoded `app.baseURL`.
5. `public/assets/stylesheets/*.css` with `:root { --color-…: … }` tokens —
   including one matching `*primary*` or `*accent*`. No tokens = lint error.
6. Images under `public/assets/…`, referenced with root-absolute `/assets/…` paths.
7. Do **not** ship: `node_modules/`, `.nuxt/`, `.output/`, `.git/`, symlinks, or
   any `app/composables/useOluxContent.ts`, `app/plugins/olux-*.client.ts`,
   `app/olux-theme.ts`, `app/pages/[...slug].vue` — the CMS generates those.

## Soft rules (drive how editable the template is)
- Editable text = plain `h1–h5, p, a, button, span` **outside** any `v-for`.
- Editable images = literal `<img src="/assets/…">` (not `:src` bindings) outside loops.
- Repeating content = `const items = [ {…}, … ]` in `<script setup>`, rendered by
  `v-for`; loop images as `` :src="`/assets/images/x/${row.img}`" ``.
- Prefer field keys the CMS labels nicely: `img, image, name, role, text, title,
  desc, price, question, answer, quote, link, url, icon, delay`.
- One plain `<h2>` headline per section block; `useHead({ title })` per page;
  Google Fonts + behaviour CDNs declared in `nuxt.config.ts` `app.head`;
  menu links as real `/paths`, not `#anchors`.

## Size limits (zip intake)
≤600 files, ≤60 MB total, ≤10 MB per file; extensions limited to source +
image/font types.

## Validate locally
```bash
php artisan template:import ./my-template --key=mytpl   # stages + lints (pending review)
php artisan templates:lint mytpl                        # re-run the linter any time
php artisan nuxt:preview-build --template=mytpl         # build the live preview
```
The importer aborts on any error-level lint finding.

## Deploying updates

Once a template is accepted, shipping an update is a single step:

- **Repo workflow (recommended):** just `git push`. The webhook re-stages,
  re-extracts, re-lints, republishes, rebuilds the renderer shell and
  refreshes every site using the template — no CMS action needed.
  (Or click **Pull latest** on the Template Submissions page.)
- **Local folder / zip / one-off:**

  ```bash
  php artisan template:deploy <key> --from=/path/to/app   # or a zip / git URL
  php artisan template:deploy <key>                       # pull from its repo
  php artisan template:deploy <key> --no-sites            # republish + build only
  ```

`template:deploy` = import/republish → catalog sync → `nuxt:preview-build`
→ idempotent refresh of every applied site (new pages, blocks, chrome,
collections, forms, booking services and assets are added; content the site
owner has edited is never overwritten). It also warns if the manifest's
hand-curated `forms` / `booking` / `collections` blocks went missing.
