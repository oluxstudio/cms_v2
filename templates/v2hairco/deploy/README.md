# Deploying v2hairco.oluxstudio.com (containerized, Traefik edge)

Architecture: ONE Traefik container owns 80/443 for the whole VPS
(`/srv/edge/`). Every site is a self-contained compose stack in
`/srv/sites/<name>/` — its domain lives in its own labels, TLS is automatic,
and no shared file is ever edited to add/remove a site.

Every push to `main` builds the static site (against cms.oluxstudio.com)
and rsyncs it into `/srv/sites/v2hairco/public` — the container serves the
new files instantly, no restart.

## One-time VPS setup

```bash
# 1. Edge (once per VPS, shared by ALL sites)
docker network create edge
mkdir -p /srv/edge && cp deploy/vps/edge-compose.yml /srv/edge/docker-compose.yml
# ⚠ port 80/443 must be free: migrate host-nginx sites first (see below), then
systemctl stop nginx && systemctl disable nginx
cd /srv/edge && docker compose up -d

# 2. The hairco site stack
mkdir -p /srv/sites/v2hairco/public
cp deploy/vps/site-compose.yml /srv/sites/v2hairco/docker-compose.yml
cp deploy/vps/site-nginx.conf  /srv/sites/v2hairco/nginx.conf
cd /srv/sites/v2hairco && docker compose up -d
```

## Migrating the CMS + existing host-nginx sites
Stopping host nginx takes down anything it served (cms.oluxstudio.com,
portfolio, petshop, …). Before the cut-over:

- **CMS**: attach the app service to the `edge` network with labels
  (`Host(\`cms.oluxstudio.com\`)` → its internal :80) via a compose override,
  and drop the host-nginx vhost. The app already serves HTTP itself.
- **Each static site**: copy `/srv/sites/<name>/` from the hairco pattern,
  point `public/` at (or copy in) its files, edit the two label lines.
- Inventory what host nginx serves: `ls /etc/nginx/sites-enabled/`.

## GitHub repo secrets
| Secret | Value |
|---|---|
| `VPS_HOST` | 72.61.17.72 |
| `VPS_USER` | root (or a deploy user that owns /srv/sites/v2hairco/public) |
| `VPS_SSH_KEY` | private key for that user |
| `OLUX_API_KEY` | *(optional)* prod management key — auto-fetches the connect.js token |

## Production CMS content (one time)
`OLUX_CMS=https://cms.oluxstudio.com OLUX_API_KEY=<prod key> npm run cms:init -- --overwrite && npm run cms:seed`

## Verify
`gh run watch -R oluxstudio/v2hairco` → `curl -I https://v2hairco.oluxstudio.com`
