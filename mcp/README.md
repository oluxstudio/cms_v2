# olux-cms-mcp

MCP (Model Context Protocol) server that lets AI agents — Claude Code, Claude
Desktop, or any MCP client — read and manage an Olux CMS site through the CMS
REST API.

## Security model

- **Bring a SCOPED token.** On the CMS settings page, generate a token limited
  to **one site**, only the **abilities** the agent needs (e.g. `posts.manage`
  for a blogging agent), and an **expiry**. The server has no privileged path:
  every call goes through the public API, so the CMS's permission checks,
  token scoping, rate limits and audit trail always apply.
- **Destructive tools refuse to run** unless the agent passes `confirm: true`.
- Prefer a read-only token (no `*.manage` abilities) when the agent only needs
  to look things up.

## Setup

```bash
cd mcp
npm install
npm run build
```

Register with Claude Code:

```bash
claude mcp add olux-cms \
  -e OLUX_CMS_URL=https://cms.oluxstudio.com \
  -e OLUX_CMS_TOKEN=your-scoped-token \
  -e OLUX_SITE=your-site-name \
  -- node /absolute/path/to/mcp/dist/index.js
```

`OLUX_SITE` is optional when the token is **site-scoped** — the server resolves
the site from the key via `/api/me` at startup. Set it explicitly only for an
unscoped token that can reach several sites.

Or Claude Desktop (`claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "olux-cms": {
      "command": "node",
      "args": ["/absolute/path/to/mcp/dist/index.js"],
      "env": {
        "OLUX_CMS_URL": "https://cms.oluxstudio.com",
        "OLUX_CMS_TOKEN": "your-scoped-token",
        "OLUX_SITE": "your-site-name"
      }
    }
  }
}
```

## Tools

Read: `whoami`, `get_meta`, `get_site_content`, `list_pages`,
`list_components`, `list_posts`, `get_post`, `list_collections`, `list_forms`,
`list_media`, `list_bookings`.

Write (token needs the matching `*.manage` ability): `create_post`,
`update_post`, `create_component`, `update_component`, `create_page`,
`update_page`, `create_form`, `create_collection`, `update_collection`,
`add_collection_item`, `update_collection_item`, `add_media_url`,
`update_booking`.

Destructive (require `confirm: true`): `delete_post`, `delete_component`,
`delete_page`, `delete_form`, `delete_collection`, `delete_collection_item`.

## Grounding resource

The server exposes an MCP resource `olux://content-model` — a cheatsheet of how
the CMS content model (pages, components, nodes, collections, forms, posts)
maps to the tools, with recipes (build a blog, a contact form, a page section
with a repeating list). MCP clients can read it to ground their prompts.
