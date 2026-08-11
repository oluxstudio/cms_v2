#!/usr/bin/env node
/**
 * Olux CMS MCP server (stdio) — wraps the CMS REST API as MCP tools so AI
 * agents can read and manage ONE site with a scoped Bearer token.
 *
 * Env:
 *   OLUX_CMS_URL    e.g. https://cms.oluxstudio.com   (default http://localhost:8000)
 *   OLUX_CMS_TOKEN  an API token — create a SCOPED one (one site, only the
 *                   abilities you need, with an expiry) on the settings page
 *   OLUX_SITE       the site name the tools operate on
 *
 * Security model: this server adds NO privileged path — every call goes
 * through the public API, so the CMS's permissions, token scoping, rate
 * limits and audit trail always apply. Destructive tools additionally
 * require confirm: true.
 */
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const BASE = (process.env.OLUX_CMS_URL ?? "http://localhost:8000").replace(/\/$/, "");
const TOKEN = process.env.OLUX_CMS_TOKEN ?? "";
let SITE = process.env.OLUX_SITE ?? "";

// OLUX_SITE is optional: a site-scoped token already knows its site, so we
// resolve it from /api/me when it isn't set explicitly.
if (!SITE && TOKEN) {
  try {
    const res = await fetch(`${BASE}/api/me`, { headers: { Authorization: `Bearer ${TOKEN}`, Accept: "application/json" } });
    if (res.ok) {
      const me = await res.json();
      SITE = me.site ?? (Array.isArray(me.sites) && me.sites.length === 1 ? me.sites[0] : "");
    }
  } catch {
    /* fall through to the error below */
  }
}

if (!SITE) {
  console.error("Set OLUX_SITE, or use a site-scoped OLUX_CMS_TOKEN so the site resolves automatically.");
  process.exit(1);
}

async function api(method: string, path: string, body?: unknown): Promise<string> {
  const res = await fetch(`${BASE}/api${path}`, {
    method,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(TOKEN ? { Authorization: `Bearer ${TOKEN}` } : {}),
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await res.text();
  if (!res.ok) {
    throw new Error(`CMS API ${res.status}: ${text.slice(0, 500)}`);
  }
  return text;
}

const server = new McpServer({ name: "olux-cms", version: "1.0.0" });

const ok = (text: string) => ({ content: [{ type: "text" as const, text }] });
const s = `/sites/${SITE}`;

/* ── Read tools ─────────────────────────────────────────────────────────── */

server.tool("whoami", "Who does the configured token act as — user, scoped site, abilities, expiry.", {}, async () =>
  ok(await api("GET", "/me")));

server.tool("get_meta", "The valid enum vocabularies the write tools accept: node types, form/collection field types, post + item statuses.", {}, async () =>
  ok(await api("GET", "/meta")));

server.tool("get_site_content", "The whole site content tree (pages → components → nodes) plus site attributes.", {}, async () =>
  ok(await api("GET", `${s}/content`)));

server.tool("list_pages", "All pages with their attribute maps.", {}, async () =>
  ok(await api("GET", `${s}/pages`)));

server.tool("list_components", "All content components with their full node lists, tags and page attachments.", {}, async () =>
  ok(await api("GET", `${s}/components`)));

server.tool("list_posts", "Published blog posts (paginated).", { per_page: z.number().int().min(1).max(50).optional() }, async ({ per_page }) =>
  ok(await api("GET", `${s}/posts${per_page ? `?per_page=${per_page}` : ""}`)));

server.tool("get_post", "One published post including its full body HTML.", { slug: z.string() }, async ({ slug }) =>
  ok(await api("GET", `${s}/posts/${encodeURIComponent(slug)}`)));

server.tool("list_collections", "Public collections with field schemas and published items.", {}, async () =>
  ok(await api("GET", `${s}/collections`)));

server.tool("list_forms", "Active forms with field schemas and submit URLs.", {}, async () =>
  ok(await api("GET", `${s}/forms`)));

server.tool("list_media", "The asset library (images, video, documents).", { type: z.enum(["image", "video", "document", "font"]).optional(), search: z.string().optional() }, async ({ type, search }) => {
  const q = new URLSearchParams();
  if (type) q.set("type", type);
  if (search) q.set("search", search);
  return ok(await api("GET", `${s}/media${q.size ? `?${q}` : ""}`));
});

server.tool("list_bookings", "Bookings (requires bookings.manage).", { status: z.enum(["pending", "confirmed", "cancelled"]).optional() }, async ({ status }) =>
  ok(await api("GET", `${s}/bookings${status ? `?status=${status}` : ""}`)));

/* ── Write tools (token needs the matching *.manage ability) ────────────── */

server.tool("create_post", "Create a blog post (defaults to draft; slug derives from the title and is immutable).", {
  title: z.string(), body: z.string().optional(), excerpt: z.string().optional(),
  cover_image: z.string().optional(), status: z.enum(["draft", "published"]).optional(),
}, async (args) => ok(await api("POST", `${s}/posts`, args)));

server.tool("update_post", "Update a post by slug (any field; drafts included).", {
  slug: z.string(), title: z.string().optional(), body: z.string().optional(), excerpt: z.string().optional(),
  cover_image: z.string().optional(), status: z.enum(["draft", "published"]).optional(),
}, async ({ slug, ...rest }) => ok(await api("PATCH", `${s}/posts/${encodeURIComponent(slug)}`, rest)));

const nodeSchema = z.object({
  label: z.string(), type: z.enum(["text", "url", "image", "number", "boolean", "color", "collection"]),
  value: z.string().optional(), parent: z.string().optional(), order: z.number().int().optional(),
});

server.tool("create_component", "Create a content component with its typed nodes; optionally attach to pages.", {
  name: z.string(), description: z.string().optional(), tags: z.array(z.string()).optional(),
  nodes: z.array(nodeSchema).optional(), page_ids: z.array(z.string()).optional(),
}, async (args) => ok(await api("POST", `${s}/components`, args)));

server.tool("update_component", "Update a component. A nodes array REPLACES all nodes; omit to keep them.", {
  id: z.string(), name: z.string().optional(), description: z.string().optional(),
  tags: z.array(z.string()).optional(), nodes: z.array(nodeSchema).optional(), page_ids: z.array(z.string()).optional(),
}, async ({ id, ...rest }) => ok(await api("PATCH", `${s}/components/${id}`, rest)));

server.tool("create_page", "Create a page (url must be unique on the site). attributes = key→value map.", {
  name: z.string(), url: z.string(), keywords: z.string().optional(),
  is_published: z.boolean().optional(), attributes: z.record(z.string(), z.string().nullable()).optional(),
}, async (args) => ok(await api("POST", `${s}/pages`, args)));

server.tool("update_page", "Update a page; attribute values of null forget that key.", {
  id: z.string(), name: z.string().optional(), url: z.string().optional(), keywords: z.string().optional(),
  is_published: z.boolean().optional(), attributes: z.record(z.string(), z.string().nullable()).optional(),
}, async ({ id, ...rest }) => ok(await api("PATCH", `${s}/pages/${id}`, rest)));

server.tool("create_form", "Create a form; its schema immediately powers the public schema + submit endpoints.", {
  name: z.string(), title: z.string().optional(), description: z.string().optional(),
  fields: z.array(z.object({
    key: z.string(), label: z.string().optional(),
    type: z.enum(["text", "email", "tel", "number", "url", "date", "textarea", "select", "radio", "checkbox"]).optional(),
    required: z.boolean().optional(), placeholder: z.string().optional(), options: z.array(z.string()).optional(),
    min: z.number().optional(), max: z.number().optional(),
  })),
}, async (args) => ok(await api("POST", `${s}/forms`, args)));

const collectionFieldSchema = z.object({
  key: z.string(), label: z.string().optional(),
  type: z.enum(["text", "email", "tel", "number", "url", "date", "textarea", "select", "radio", "checkbox"]).optional(),
  required: z.boolean().optional(), placeholder: z.string().optional(), options: z.array(z.string()).optional(),
  min: z.number().optional(), max: z.number().optional(),
});

server.tool("create_collection", "Create a structured content collection (a field schema + items). Add items afterwards with add_collection_item.", {
  name: z.string(), type: z.string().optional(), description: z.string().optional(),
  fields: z.array(collectionFieldSchema).optional(),
  is_public: z.boolean().optional(), allow_submit: z.boolean().optional(),
}, async (args) => ok(await api("POST", `${s}/collections`, args)));

server.tool("update_collection", "Update a collection's name/description/fields/visibility. A fields array replaces the schema.", {
  id: z.string(), name: z.string().optional(), type: z.string().optional(), description: z.string().optional(),
  fields: z.array(collectionFieldSchema).optional(), is_public: z.boolean().optional(), allow_submit: z.boolean().optional(),
}, async ({ id, ...rest }) => ok(await api("PATCH", `${s}/collections/${id}`, rest)));

server.tool("add_collection_item", "Add an item to a collection (status defaults to published). data keys should match the collection's field keys.", {
  collection_id: z.string(), data: z.record(z.string(), z.unknown()),
  status: z.enum(["published", "pending", "archived"]).optional(),
}, async ({ collection_id, ...rest }) => ok(await api("POST", `${s}/collections/${collection_id}/items`, rest)));

server.tool("update_collection_item", "Update one item's data or status.", {
  collection_id: z.string(), item_id: z.string(),
  data: z.record(z.string(), z.unknown()).optional(),
  status: z.enum(["published", "pending", "archived"]).optional(),
}, async ({ collection_id, item_id, ...rest }) => ok(await api("PATCH", `${s}/collections/${collection_id}/items/${item_id}`, rest)));

server.tool("add_media_url", "Register an external asset by URL in the media library.", {
  url: z.string(), name: z.string().optional(), type: z.enum(["image", "video", "document", "font"]).optional(), alt: z.string().optional(),
}, async (args) => ok(await api("POST", `${s}/media`, args)));

server.tool("update_booking", "Update a booking's status (confirmed emails the customer; cancelled too).", {
  id: z.string(), status: z.enum(["pending", "confirmed", "cancelled"]),
}, async ({ id, ...rest }) => ok(await api("PATCH", `${s}/bookings/${id}`, rest)));

/* ── Destructive tools — refuse without confirm: true ───────────────────── */

const needsConfirm = (confirm: boolean | undefined) => {
  if (confirm !== true) {
    throw new Error("Refused: destructive action. Pass confirm: true to proceed.");
  }
};

server.tool("delete_post", "DELETE a post by slug. Requires confirm: true.", { slug: z.string(), confirm: z.boolean().optional() }, async ({ slug, confirm }) => {
  needsConfirm(confirm);
  return ok(await api("DELETE", `${s}/posts/${encodeURIComponent(slug)}`));
});

server.tool("delete_component", "DELETE a component (nodes + page attachments go with it). Requires confirm: true.", { id: z.string(), confirm: z.boolean().optional() }, async ({ id, confirm }) => {
  needsConfirm(confirm);
  return ok(await api("DELETE", `${s}/components/${id}`));
});

server.tool("delete_page", "DELETE a page. Requires confirm: true.", { id: z.string(), confirm: z.boolean().optional() }, async ({ id, confirm }) => {
  needsConfirm(confirm);
  return ok(await api("DELETE", `${s}/pages/${id}`));
});

server.tool("delete_form", "DELETE a form and ALL its responses. Requires confirm: true.", { name: z.string(), confirm: z.boolean().optional() }, async ({ name, confirm }) => {
  needsConfirm(confirm);
  return ok(await api("DELETE", `${s}/forms/${encodeURIComponent(name)}`));
});

server.tool("delete_collection", "DELETE a collection and ALL its items. Requires confirm: true.", { id: z.string(), confirm: z.boolean().optional() }, async ({ id, confirm }) => {
  needsConfirm(confirm);
  return ok(await api("DELETE", `${s}/collections/${id}`));
});

server.tool("delete_collection_item", "DELETE one item from a collection. Requires confirm: true.", { collection_id: z.string(), item_id: z.string(), confirm: z.boolean().optional() }, async ({ collection_id, item_id, confirm }) => {
  needsConfirm(confirm);
  return ok(await api("DELETE", `${s}/collections/${collection_id}/items/${item_id}`));
});

/* ── Grounding resource — how the content model maps to the tools ───────── */

const CONTENT_MODEL = `# Olux CMS content model — a map for building content

A **Site** owns Pages, Components, Collections, Forms and Posts.

- **Page** — a route (unique \`url\` per site) with SEO/EAV \`attributes\`. Page
  content is the ordered Components attached to it. Tools: create_page /
  update_page / delete_page. Attach a component to pages with the component's
  \`page_ids\`.
- **Component** — a named bag of typed **Nodes**; reusable, standalone or
  page-attached. Use it for a section's SCALAR content (headings, body copy,
  a CTA label, a single image). Node \`type\` ∈ node_types from get_meta
  (text, url, image, number, boolean, color, collection). Flat nodes use
  \`parent: "0"\`. Tools: create_component / update_component (a nodes array
  REPLACES all nodes) / delete_component.
- **Collection** — a field schema + repeating **items**. Use it for LISTS
  (team members, FAQs, stats, gallery). Create the schema with
  create_collection (\`fields[]\`), then add rows with add_collection_item
  (\`data\` keys = field keys). Field \`type\` ∈ collection_field_types.
- **Form** — a field schema that immediately powers the public schema + submit
  endpoints; visitor submissions land in the CRM. Tools: create_form (name is
  slugged + unique). Field \`type\` ∈ form_field_types.
- **Post** — a blog article (title → auto slug, body HTML, draft|published).
  Tools: create_post / update_post / delete_post.

## Recipes
- **Blog** → create_post ×N (set status:"published" to go live).
- **Contact form** → create_form { name, fields:[{key:"name"},{key:"email",type:"email",required:true},{key:"message",type:"textarea"}] }.
- **A page section with a repeating list** (e.g. About + team) →
  create_component for the scalar copy, create_collection + add_collection_item
  for the list, create_page (or reuse home), then attach the component via
  its \`page_ids\`.

## Rules of thumb
- Call get_meta once to learn the exact enum vocabularies before writing.
- Scalar/one-off content → Component nodes. Repeating rows → Collection items.
- Deletes require \`confirm: true\`. The token's abilities + site scope are
  enforced server-side; a 403 means the token lacks that \`*.manage\` ability.`;

server.resource(
  "content-model",
  "olux://content-model",
  { mimeType: "text/markdown", description: "How the Olux CMS content model (pages, components, nodes, collections, forms, posts) maps to these tools, with recipes." },
  async (uri) => ({ contents: [{ uri: uri.href, mimeType: "text/markdown", text: CONTENT_MODEL }] }),
);

const transport = new StdioServerTransport();
await server.connect(transport);
console.error(`olux-cms-mcp ready — site "${SITE}" via ${BASE}`);
