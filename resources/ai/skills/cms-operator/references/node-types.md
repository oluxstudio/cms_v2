# Node types — choosing the right field type

A node's `type` controls how the public site renders the value, so pick the type that matches the
content. Defaulting everything to `text` "works" but produces wrong-looking output (a price as plain
text won't format, a link won't be clickable, a colour won't paint).

| type | use it for | value looks like | renders as |
|---|---|---|---|
| `text` | headings, paragraphs, labels, any prose | "Welcome to Money Hill" | text (line breaks kept) |
| `number` | prices, counts, stats, durations | "19.99", "232" | a formatted number |
| `boolean` | on/off flags, "featured?", "published?" | "true" / "false" | a yes/no badge |
| `color` | brand colours, backgrounds, accents | "#6366f1" | a colour swatch |
| `url` | links, buttons, external references | "https://example.com" | a clickable link |
| `image` | logos, photos, hero art, icons | "/storage/media/site/hero.jpg" | an `<img>` |

## Rules of thumb
- A **price** is `number`, never text — so it formats and so totals make sense.
- A **link or button target** is `url` — so it renders clickable and gets validated.
- A **brand/accent colour** is `color` — so the renderer can paint it.
- A **logo, hero, or photo** is `image`, and the value should be a media URL or path on this site.
- Everything human-readable (titles, body copy, names, CTAs' visible text) is `text`.

## Nesting (the `parent` attribute)
A node's `parent` is `0` by default. Set it to another node's label/id to nest — useful for repeating
structures: a "Cards" parent node with several child nodes (each a card's title/body), or a "Pricing"
parent with child plan nodes. Build the parent first, then add children that point at it. Keep nesting
shallow and obvious; deep trees are hard for the user to reason about.
