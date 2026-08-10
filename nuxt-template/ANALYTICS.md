# Visitor analytics beacon

Exported sites track visits automatically: `app/plugins/track.client.ts` reads
`public/analytics-config.json` (baked at export with your CMS URL + site name)
and pings `POST {trackBase}/api/sites/{site}/track` on every page view. Traffic
then shows up under **Analytics** in the CMS admin.

Nothing to do for exported sites. For that to record data, the CMS must have a
MaxMind **GeoLite2-City** database installed (free) for country/city — without
it, visits are still recorded but the map/location charts stay empty. Place the
`.mmdb` at `storage/app/geoip/GeoLite2-City.mmdb` (or set `GEOIP_DB_PATH`).

## Adding tracking to a site that isn't exported from the template

Drop this into the site's HTML (e.g. Nuxt `app.vue` `useHead`, or a `<script>`
in the page `<head>`). Replace the two values:

```html
<script>
(function () {
  var TRACK_BASE = 'https://cms.oluxstudio.com'; // your CMS URL
  var SITE = 'deve-site';                          // your site name
  function sid() {
    var s = sessionStorage.getItem('_ovid');
    if (!s) { s = Math.random().toString(36).slice(2) + Date.now().toString(36); sessionStorage.setItem('_ovid', s); }
    return s;
  }
  function beacon() {
    var body = JSON.stringify({
      path: location.pathname + location.search,
      referrer: document.referrer || null,
      language: navigator.language,
      session_id: sid()
    });
    var url = TRACK_BASE.replace(/\/$/, '') + '/api/sites/' + encodeURIComponent(SITE) + '/track';
    try {
      var blob = new Blob([body], { type: 'text/plain' }); // CORS-safelisted, no preflight
      if (navigator.sendBeacon && navigator.sendBeacon(url, blob)) return;
      fetch(url, { method: 'POST', body: body, headers: { 'Content-Type': 'text/plain' }, keepalive: true, mode: 'no-cors' });
    } catch (e) {}
  }
  beacon();
  // For SPA route changes, call beacon() again after each navigation.
  window.addEventListener('popstate', beacon);
})();
</script>
```

The beacon's `Origin` must match the site's `domain` (or an entry in the site's
`allowed_origins` attribute), or the CMS rejects it with 403.
