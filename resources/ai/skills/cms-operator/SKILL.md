---
name: cms-operator
description: >-
  Operate a single selected site inside Olux CMS by doing CRUD over its pages, components, and
  component node values. Use this whenever the user asks to create/rename/publish a page, add or
  edit a component, change visible content (headings, text, prices, links, colours, images),
  build forms, apply a template, or toggle a site feature — i.e. almost every site-editing prompt.
  This is the core skill behind the in-app AI prompt bar; reach for it on any "change/add/show
  something on my site" request, even when the user doesn't name pages, components, or nodes.
---

# CMS Operator

This skill turns a natural-language instruction into concrete CRUD actions on the **currently
selected site**. Its whole job is to keep you anchored in the data model so you act on the right
thing: **Site → Pages → Components → Nodes (key/value fields)**.

## The mental model (never lose this)
- A **page** is an ordered list of **components**.
- A **component** is a reusable block holding many **nodes**.
- A **node** is one field: `label` (key), `value`, `type`, `description`, and `parent` (0 by default,
  or another node's id to nest). Nodes form a tree inside a component.
- **What a visitor sees = the node values of the components on the page.** So "edit the content" is
  almost always "update a node value", and "add content" is "add a node" (or a component).

## The operating loop
1. **Parse intent → locate target.** Decide whether the request is about a *page*, a *component*, or
   a specific *node value*. Identify the named (or implied) target.
2. **Read if unsure.** Use the read tools (`site_status`, `list_pages`, `list_components`) to confirm
   the real page/component/node before mutating. Don't invent labels that may not exist.
3. **Act with one tool call.** Create, update, or apply — using the tool that matches the operation.
4. **Confirm in one line.** State exactly what changed, in the user's words.

## Mapping intents to operations
| User says… | Operation | Tool |
|---|---|---|
| "add an About page" | create page | `create_page` |
| "when was this site created?" | site info | `site_info` |
| "tell me about this site" | site info | `site_info` |
| "what forms do I have?" | read forms | `list_forms` |
| "publish / unpublish X" | toggle page state | `publish_page` |
| "what pages do I have?" | read | `list_pages` |
| "add a Hero block" | create component | `create_component` |
| "what's on the site?" | read | `list_components` / `site_status` |
| "change the heading to Y" | **update node value** | `update_node_value` |
| "add a subtitle field" | add node (optionally nested) | `add_node` |
| "make a contact form with name and email" | form + fields | `create_form` → `add_form_field` × N |
| "add product / list products" | store CRUD (if Store on) | `create_product` / `list_products` |
| "enable store / donations" | toggle feature (owner/admin) | `toggle_feature` |
| "use the nexonix template" | **replace** structure (owner/admin) | `apply_template` |
| "wireframe / plan / sketch a landing page" | draft a wireframe for approval | `list_block_types` → `create_wireframe` |
| "approve / build that wireframe" | materialise a wireframe (owner/admin) | `commit_wireframe` |

> The tool *names* above are the canonical operations. The runtime exposes them to you as callable
> tools; if a tool isn't present, the user's role or an unenabled feature is the reason — say so.

## Editing node values — the most common job
Read `references/crud-recipes.md` for worked recipes (rename a heading, change a price, swap an image,
nest cards under a parent node, build a page from scratch). Read `references/node-types.md` to choose
the correct node `type` — picking `number` for prices, `url` for links, `color` for brand colours,
and `image` for media makes the public render correct instead of dumping everything as text.

## CRITICAL: content creation ALWAYS chains tools

### Rule 1 — "create page with content" chains: page → component(s) → nodes
When asked to **create a page AND give it content** (e.g. "create an About page for a dentist", "build a landing page about coffee"), you MUST:
1. `create_page` — create the page
2. `create_component` — one or more named components (Hero, About, Services, FAQ, CTA, etc.)
   **ALWAYS pass `page: "<page name>"` so the component is attached to the page immediately.**
3. `add_node` — MULTIPLE nodes per component with REAL, topic-appropriate values

**Never stop after `create_page` alone when content was requested.**
**Never call `create_component` without `page` when creating content for a page — the component must be attached.**

### Rule 2 — "create content for [page]" finds the page first, then creates components
Phrases like: "create content for the About Us page", "add content to Home page about our services",
"populate the Contact page with a mission statement" mean:
1. `list_pages` — check if the named page already exists
2. If the page exists: DO NOT create a new page. Proceed directly to step 3.
   If it does NOT exist: `create_page` to create it.
3. `create_component` with `page: "<page name>"` — creates component AND attaches it to the page
4. `add_node` — add multiple nodes with REAL content values relevant to the description

**The page name in the prompt is the target page to find OR create — never make up a different page name.**
**Always pass `page` to `create_component` so the component appears on the page, not floating detached.**

A minimum content page: at least one component with 3–6 populated nodes (heading, subheading, body, etc.).

### Rule 3 — "wireframe / plan / structure" drafts blocks for approval (does NOT build)
When the user asks to **wireframe, plan, sketch, structure, or draft a layout** for a page or site
(e.g. "wireframe a SaaS landing page", "plan out a portfolio homepage"), do NOT create pages/components
directly. Instead:
1. `list_block_types` — if you're unsure which catalog blocks exist (hero, features, pricing, testimonials, team, stats, faq, cta, gallery, contact, newsletter…).
2. `create_wireframe` — pass a `title` and an **ordered** `blocks` list (top-to-bottom) of catalog `type` keys, each with an optional `label`. This creates a *draft* the user reviews.
3. Tell the user to open the **Wireframes** page to review and **Approve & Build** it.

Only call `commit_wireframe` when the user explicitly says to approve/build it from chat. Wireframing is
the "plan first" path; the regular page→component→node chain (Rules 1–2) is the "build now" path.

## Guardrails
- **Stay on the selected site.** Never touch another site or account.
- **Respect roles.** `toggle_feature` and `apply_template` are owner/admin-only. If denied, say so.
- **Destructive = confirm.** `apply_template` wipes existing pages/components — only on clear intent.
- **No invented data for structure.** For requested *topics* (e.g. "a dentist page"), invent realistic, appropriate content values — that IS the job.

## Mapping intents to operations
| User says… | Operation | Tool chain |
|---|---|---|
| "add an About page" | create page only | `create_page` |
| "create an About page **with content**" | page + content | `create_page` → `create_component` → N×`add_node` |
| "create a page about [topic]" | page + content | `create_page` → `create_component` → N×`add_node` |
| "create content for About Us page that describes X" | **find or create** page + build content | `list_pages` → (optional `create_page`) → `create_component` → N×`add_node` |
| "add content to the Home page about services" | find page + add content | `list_pages` → `create_component` → N×`add_node` |
| "populate the [X] page" | find page + add content | `list_pages` → `create_component` → N×`add_node` |
| "publish / unpublish X" | toggle page state | `publish_page` |
| "what pages do I have?" | read | `list_pages` |
| "add a Hero block" | create component | `create_component` |
| "what's on the site?" | read | `list_components` / `site_status` |
| "change the heading to Y" | update node value | `update_node_value` |
| "add a subtitle field" | add node | `add_node` |
| "make a contact form" | create form + add fields | `create_form` → N×`add_form_field` |
| "make a contact form with name, email, message" | form + all fields | `create_form` → `add_form_field` ×3 |
| "add product / list products" | store CRUD (if Store on) | `create_product` / `list_products` |
| "enable store / donations" | toggle feature (owner/admin) | `toggle_feature` |
| "use the nexonix template" | **replace** structure (owner/admin) | `apply_template` |
| "wireframe a landing page" | draft blocks for approval | `list_block_types` → `create_wireframe` |
| "approve / build that wireframe" | materialise (owner/admin) | `commit_wireframe` |

## Reference files
- `references/node-types.md` — the six node types and when to use each.
- `references/crud-recipes.md` — step-by-step recipes for common edits.
