<?php

namespace App\Templates;

interface TemplateContract
{
    /** Unique machine key, e.g. "blog" */
    public function key(): string;

    /** Display name */
    public function name(): string;

    /** Short description shown in the gallery */
    public function description(): string;

    /** Category label, e.g. "Business", "Blog", "Portfolio" */
    public function category(): string;

    /** Tailwind gradient classes for the card preview strip */
    public function gradientClass(): string;

    /** Accent hex colour for the category badge */
    public function accentColor(): string;

    /** Author / designer name */
    public function author(): string;

    /** Semantic version string, e.g. "1.0.0" */
    public function version(): string;

    /** ISO date string when the template was published, e.g. "2025-01-15" */
    public function createdAt(): string;

    /** Short tag list for filtering / discovery */
    public function tags(): array;

    /** Bullet-point feature highlights shown on the detail screen */
    public function features(): array;

    /**
     * Page definitions.
     * Each entry:
     * [
     *   'name'       => string,
     *   'url'        => string,
     *   'keywords'   => string,
     *   'components' => [
     *     [
     *       'name'        => string,
     *       'description' => string,
     *       'nodes'       => [
     *         ['label' => string, 'type' => string, 'value' => string, 'order' => int, 'description' => string],
     *         ...
     *       ],
     *     ],
     *     ...
     *   ],
     * ]
     *
     * Use {site_name} in any value — it is replaced with the real site name at apply-time.
     */
    public function pages(): array;
}
