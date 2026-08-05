# Template packages

Each subfolder here is a **split-file template package** — the canonical format for
authoring a template (and the shape an uploaded `.zip` is unpacked into). A package
is discovered by `App\Templates\TemplatePackage` and exposed through
`TemplateRegistry`, so it shows up in the Marketplace as installable and previewable.

## Layout

```
{key}/
├── template.json        # manifest: key, name, description, category, author,
│                        #   version, accentColor, gradientClass, tags[],
│                        #   features[], thumbnail, pages[] (page order)
├── tokens/
│   ├── colors.json      # accent, navy, surface, text, muted   (theme colours)
│   └── sizes.json       # radius, base_size                    (theme sizes)
├── fonts/
│   └── fonts.json       # { "primary": { family, source, url, weights[], fallback } }
├── css/
│   └── template.css     # template-specific CSS, layered on install (uses --vars)
├── assets/              # placeholder images referenced by the pages
├── pages/
│   └── {slug}.json      # one file per page: { name, url, keywords, blocks[] }
├── playground.html      # standalone design sandbox (also used as the preview)
└── thumbnail.svg        # card screenshot (optional; png/jpg/webp/avif also ok)
```

## Notes

- **Tokens → theme.** `tokens/colors.json` + `tokens/sizes.json` keys map 1:1 onto the
  site theme (`accent`, `navy`, `surface`, `text`, `muted`, `radius`, `base_size`);
  `fonts/fonts.json` `primary.family` becomes the theme `font`. Keep token keys to
  that vocabulary so they apply cleanly.
- **Blocks.** Each page's `blocks[]` uses the same shape as the block catalog —
  `{ type, variant, name, nodes[] }`. `{site_name}` in any value is substituted at
  apply time. See `neso/pages/home.json` for a worked example (incl. the `neso`
  hero variant).
- **Preview.** Drop the rendered package at `public/vue-templates/{key}/index.html`
  (the bundled `playground.html` doubles as this) and the Marketplace gets a live
  Preview button.
- **Thumbnail.** Put `public/template-thumbnails/{key}.{svg|png|…}` for the card image.

## Lifecycle (in progress)

Install = append the package's pages + a layout + theme tokens + fonts + assets to a
site (tracked); uninstall = remove exactly those, leaving site defaults. The token /
font / asset layering and the in-app studio are being built in later phases; today an
install applies pages + theme via `TemplateService`.
