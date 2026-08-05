# Template Marketplace — Architecture Plan

Goal: **any user can create a template, set it free or priced, publish it, and other
users browse, (buy), and install it.** This document is the target architecture and a
phased path from today's state.

What carries over from today (do **not** rebuild): the **split-file package format**
(`tokens/ fonts/ css/ pages/ assets/ template.json`), `TemplatePackage`/`ArrayTemplate`,
and the apply pipeline (`TemplateService` → blocks/nodes). What changes: the **source of
truth** (filesystem registry → DB + object storage), the **catalog**, and new
**identity / commerce / moderation** layers.

---

## 1. Domain model (schema)

### `templates` — the global catalog entity (one per creator template)
| column | type | notes |
|---|---|---|
| id | bigint pk | |
| uuid | uuid | public, unguessable id |
| user_id | fk users | the **creator** |
| name, slug | string | slug unique per creator |
| description | text | |
| category | string | indexed |
| tags | json | |
| status | enum | `draft \| in_review \| published \| rejected \| unlisted \| suspended` |
| price_cents | int | `0` = free |
| currency | char(3) | default platform currency |
| latest_version_id | fk template_versions | published pointer |
| accent_color, gradient_class | string | card styling |
| thumbnail_path | string | object-storage key |
| installs_count, rating_avg, rating_count | int/decimal | denormalized for sorting |
| published_at, created_at, updated_at | ts | |

Indexes: `(status, category)`, `(status, price_cents)`, fulltext on `name,description`.

### `template_versions` — immutable, versioned payload
| column | type | notes |
|---|---|---|
| id | bigint pk | |
| template_id | fk | |
| version | string | semver |
| manifest | json | meta |
| tokens | json | colors + sizes (kept in DB — small, needed for cards/preview) |
| fonts | json | |
| pages | json **or** package_path | see §2 (hybrid) |
| css | text | template CSS (sanitized) |
| assets_manifest | json | `[ {path, mime, size, key} ]` |
| package_path | string | object-storage key to the full `.zip` |
| changelog | text | |
| status | enum | `draft \| in_review \| published \| rejected` |
| created_at | ts | |

Installs **pin** a `template_version_id` (update-safe).

### `template_entitlements` — who may install
| column | type | notes |
|---|---|---|
| id | bigint pk | |
| user_id | fk | the buyer/owner |
| template_id | fk | |
| source | enum | `free \| purchase \| granted` |
| purchase_id | fk nullable | |
| created_at | ts | |
Unique `(user_id, template_id)`. Free template "Get" creates one directly.

### `template_purchases` — the sale record
| column | type | notes |
|---|---|---|
| id, uuid | | |
| template_id, template_version_id | fk | |
| user_id | fk | buyer |
| price_cents, currency | | snapshot at purchase |
| platform_fee_cents, creator_amount_cents | | split |
| stripe_checkout_session_id, stripe_payment_intent_id | string | |
| status | enum | `pending \| paid \| refunded` |
| purchased_at | ts | |

### `creator_profiles` (or columns on users)
`user_id, display_name, stripe_connect_account_id, payouts_enabled (bool), charges_enabled (bool)`.

### `site_templates` (EXISTS — extend)
Add `template_id` (fk, nullable), `template_version_id` (fk, nullable). `source` becomes
`builtin | catalog | custom`. Keep the `payload` copy so installs stay self-contained.

### `template_reviews` (moderation log, optional)
`template_version_id, reviewer_id, status, notes, created_at`.

---

## 2. Storage (object storage / S3 + CDN)

Local `public/` does not survive multi-instance or deploys for UGC. Add an `s3` disk
(`config/filesystems.php`) and store:

```
templates/{uuid}/thumbnail.{ext}                 (public, CDN)
templates/{uuid}/preview/index.html              (public, CDN)  ← rendered preview
templates/{uuid}/versions/{version}/package.zip  (private, signed URL on download)
templates/{uuid}/versions/{version}/assets/…     (public, CDN)
```

**Hybrid payload**: keep `tokens` + `manifest` (+ small `pages`) in DB for fast
catalog/preview; keep the full `.zip` and `assets/` in S3 for install/download. First-party
seeds can stay in `resources/templates/` and be imported into the catalog.

---

## 3. Catalog service (replaces `TemplateRegistry::all()` for the marketplace)

`TemplateRegistry` is fine for first-party **seeding** but cannot back a marketplace
(filesystem scan, in-memory, no paging). Add `App\Services\TemplateCatalog`:

- `browse(filters, sort, page)` — published only; filter by category/tag/price/free;
  search `name,description`; sort popular/new/price; **paginate**; **cache** pages.
- `show(uuid)` — detail + latest version.
- Built-ins are seeded as `templates` owned by a `system` user (published, free) so the
  catalog is the single source.

---

## 4. Authoring, versioning, publishing

- **Studio** (the in-app playground) edits a **draft** `template_version`; "upload .zip"
  also creates a draft version (importer → version, not per-site row).
- **Publish**: `draft → in_review → published` (or `rejected`). Publishing sets
  `templates.latest_version_id`.
- **Versioning**: edits after publish create a **new** version; existing installs keep
  their pinned version and can opt to update.

---

## 5. Commerce (Stripe **Connect**)

Platform-level Stripe (distinct from per-site `SitePaymentSettings`, which is each site's
own store). New env: platform secret, Connect client id, platform webhook secret.

- **Creator onboarding**: Stripe Connect **Express** account link; store
  `stripe_connect_account_id`; gate "set a price" on `charges_enabled`.
- **Buy (paid)**: Checkout Session with **destination charge** —
  `payment_intent_data.application_fee_amount` (platform fee) +
  `transfer_data.destination = creatorAccount`. Webhook
  `checkout.session.completed` → `template_purchases.paid` + create entitlement.
- **Get (free)**: create entitlement directly, no Stripe.
- **Refund** → mark purchase refunded → revoke entitlement.
- **Install gate**: server-side entitlement check (free or paid) before creating the
  `site_templates` install + before issuing a signed package download URL.

> If you want to launch sooner: ship **free-only** (entitlements, no Stripe) first, then
> add Connect. The schema above already supports both.

---

## 6. Install / apply lifecycle (reversible — also the deferred "Phase 2")

Install (entitled) → copy the pinned version's pages/tokens/css/fonts into the site and
copy assets into the site's asset space; record exactly what was added. **Apply = append**
(tracked); **uninstall = remove what was added**, site defaults remain. Copy + version-pin
(not live reference) so a creator unpublishing/updating can't break a dependent site.

---

## 7. Security & moderation checklist (UGC is the risk surface)

- **Zip import**: cap total size, file count, per-file size; reject path traversal
  (`../`, absolute paths) and disallowed types (only json/css/images/woff2); reject
  php/js/html-with-script; guard against zip bombs.
- **CSS**: parse + sanitize — strip `@import`, `expression()`, `url(javascript:)`;
  rewrite/allowlist external `url()`; **scope** the template CSS to its container so it
  can't restyle the admin/other sites.
- **Pages JSON**: validate against the block catalog (known `type`/`variant`); drop
  unknown; ensure text renders **escaped** (no raw-HTML node type from untrusted creators).
- **Assets**: validate by **content** MIME, re-encode images; **sanitize or disallow SVG**
  from untrusted creators (SVG can carry JS).
- **Fonts**: allowlist (Google Fonts) or validated uploaded woff2; no arbitrary remote
  font URLs.
- **Process**: moderation queue before `published`; rate-limit upload/publish; report/
  takedown + `suspended` status; signed URLs + entitlement checks for paid downloads.

---

## 8. Migration from today

1. Add tables (`templates`, `template_versions`, `template_purchases`,
   `template_entitlements`, `creator_profiles`); extend `site_templates`.
2. Seed `resources/templates/*` (e.g. Neso) + the class templates into `templates` owned
   by a `system` user (published, free).
3. Point the Marketplace "available" list at `TemplateCatalog` (DB) instead of
   `TemplateRegistry::all()`.
4. Move thumbnails/assets/preview to the `s3` disk (keep `public/` for first-party seeds
   during transition).

Nothing already built is wasted: format, `TemplatePackage`, importer (hardened), apply
pipeline, and `site_templates` all carry forward.

---

## 9. Phased roadmap

- **P0 (done)** — split-file package format, first-party packages, per-site install (replace), marketplace tab, preview.
- **P1** — reversible append install + token layering + font loading on rendered site + asset copy. *(Valuable regardless of marketplace.)*
- **P2** — templates as DB catalog (`templates`/`template_versions`), seed built-ins, `TemplateCatalog` (search/paginate/cache), S3 storage.
- **P3** — creator identity + studio publishing + versioning + moderation queue + **UGC security hardening**.
- **P4** — commerce: pricing, free "Get" entitlements, Stripe **Connect** onboarding + paid checkout + webhooks + entitlement-gated install + refunds.
- **P5** — ratings, search ranking, creator payouts dashboard, analytics.

## 10. Open decisions (confirm before P4)
1. **Payments**: full Stripe **Connect** (pay creators) — recommended — vs platform-only
   sales to start. (Schema supports both; can launch free-only first.)
2. **Payload storage**: hybrid (tokens/manifest in DB, zip+assets in S3) — recommended.
3. **Install**: copy + version-pin — recommended — vs live reference.
