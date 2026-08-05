# Template thumbnails

Drop a screenshot here named after the template's key to use it as the card /
banner thumbnail on the Templates page. Supported extensions (first match wins):

    {key}.png  ·  {key}.jpg  ·  {key}.jpeg  ·  {key}.webp  ·  {key}.avif

Examples:

    neso.png        → the "Neso — IT Solutions" template
    business.png    → the "Business" template

Recommended: a wide screenshot of the home page (≈1600×1000, shown
`object-cover object-top`). No image → the card falls back to its gradient.

A template class can also override detection with a `public function thumbnail(): ?string`
returning an absolute URL (see App\Templates\TemplateContract usage in SiteTemplatesPage).
