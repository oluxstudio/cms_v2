# Instant site subdomains (`{site}.oluxstudio.com`)

Every site is reachable at `https://{site-name}.oluxstudio.com` the moment it
exists — the signup wizard ends on that address, and "Go live" on a custom
domain stays optional. Nothing is built per site: `ServeLiveSite` resolves the
Host header to the Site (`Site::forSubdomainHost`) and serves its template
renderer shell, exactly like a live custom domain.

## One-time setup (Cloudflare in front of the VPS)

Wildcard hosts need a wildcard DNS record **and** wildcard TLS. Let's Encrypt
only issues wildcard certificates via DNS-01, which Traefik can't do against
Hostinger DNS — so the domain moves to Cloudflare (free plan), which proxies
`*.oluxstudio.com` with its own edge certificate.

### 1. Add the zone in Cloudflare and recreate every record

Current records (inventoried 2026-08-27) — recreate all of them:

| Type | Name | Value | Proxy |
|---|---|---|---|
| A | `@` | `72.61.17.72` | Proxied |
| CNAME | `www` | `oluxstudio.com` | Proxied |
| A | `cms` | `72.61.17.72` | Proxied |
| A | `hairco` | `72.61.17.72` | Proxied |
| A | `v2hairco` | `72.61.17.72` | Proxied |
| **A** | **`*`** | **`72.61.17.72`** | **Proxied** ← the wildcard |
| CNAME | `autoconfig` | `autoconfig.mail.hostinger.com` | DNS only |
| CNAME | `autodiscover` | `autodiscover.mail.hostinger.com` | DNS only |
| MX | `@` | `mx1.hostinger.com` (5), `mx2.hostinger.com` (10) | — |
| TXT | `@` | `v=spf1 include:_spf.mlsend.com include:_spf.mail.hostinger.com ~all` | — |
| TXT | `@` | `mailerlite-domain-verification=163a1670b8adf78bad592a0be5b33ef441197bf0` | — |
| TXT | `_dmarc` | `v=DMARC1; p=none` | — |
| CNAME | `oyj2aybsk3mr3evxaqu43i6tcwtiyquu._domainkey` | `oyj2aybsk3mr3evxaqu43i6tcwtiyquu.dkim.amazonses.com` | DNS only |
| CNAME | `h7ffg4zwxhab3mz7lz6h66m75l6ugr3r._domainkey` | `h7ffg4zwxhab3mz7lz6h66m75l6ugr3r.dkim.amazonses.com` | DNS only |
| CNAME | `w4db3zatwczpylcbtrtmxztcytz2ymnw._domainkey` | `w4db3zatwczpylcbtrtmxztcytz2ymnw.dkim.amazonses.com` | DNS only |
| CNAME | `e5gbwlv7qniazvhpgv5ryff7s37gyehj._domainkey.cms` | `e5gbwlv7qniazvhpgv5ryff7s37gyehj.dkim.amazonses.com` | DNS only |
| CNAME | `awyowgmdhbr5kq4dkjmuctbro3caod3p._domainkey.cms` | `awyowgmdhbr5kq4dkjmuctbro3caod3p.dkim.amazonses.com` | DNS only |
| CNAME | `oqfwacxmqondstgxp7ayp23k6ae56pgn._domainkey.cms` | `oqfwacxmqondstgxp7ayp23k6ae56pgn.dkim.amazonses.com` | DNS only |

(If Resend is added later, its `resend._domainkey` TXT and `send` MX/TXT go
here too.)

### 2. Cloudflare settings
- **SSL/TLS → Full** (not *Flexible*, not *Full (strict)*): Cloudflare talks
  HTTPS to Traefik, which answers wildcard hosts with its default certificate.
- **Always Use HTTPS**: on.

### 3. Switch nameservers at Hostinger
Domains → oluxstudio.com → Nameservers → the two Cloudflare nameservers.
Propagation is usually under an hour; existing hosts keep working throughout
because the same IP is behind both.

### 4. Server / app
- `docker-compose.traefik.yml` already carries the `cms-sites` wildcard router
  (`HostRegexp` → the app, priority 1 so explicit hosts win).
- In the prod `.env`: `PLATFORM_SUBDOMAIN_BASE=oluxstudio.com`, then
  `php artisan optimize` (config is cached). `PLATFORM_RESERVED_SUBDOMAINS`
  can add extra labels; www/cms/mail/hairco/v2hairco… are reserved by default.
- Verify: `curl -I https://anything-here.oluxstudio.com` → platform response;
  create a site → `https://{name}.oluxstudio.com` shows it.

## Behaviour
- Subdomain serving is always on for an existing site; the `live` flag only
  governs custom domains.
- Reserved labels can't be claimed at signup (`Site::validSubdomainLabel`).
- The Caddy on-demand-TLS gate (`/caddy/ask`, used in edge mode) approves
  subdomain hosts as well as live custom domains.
