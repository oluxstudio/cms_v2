<?php

namespace App\Templates;

/**
 * A TemplateContract backed by plain arrays rather than a hard-coded class — used
 * for custom templates imported from a .zip (or captured from a site). Lets the
 * same apply/scaffold pipeline drive built-in and user templates identically.
 */
class ArrayTemplate implements TemplateContract
{
    /**
     * @param  array<string,mixed>  $meta  key, name, description, category, gradientClass,
     *                                     accentColor, author, version, createdAt, tags[], features[]
     * @param  array<int,mixed>  $pages  page definitions in the TemplateContract::pages() shape
     * @param  array<string,mixed>  $theme  theme values (font/accent/…); empty for none
     */
    public function __construct(
        private array $meta,
        private array $pages,
        private array $theme = [],
        private ?string $thumbnailUrl = null,
    ) {}

    private function m(string $key, mixed $default = ''): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function key(): string
    {
        return (string) $this->m('key', 'custom');
    }

    public function name(): string
    {
        return (string) $this->m('name', 'Custom Template');
    }

    public function description(): string
    {
        return (string) $this->m('description', '');
    }

    public function category(): string
    {
        return (string) $this->m('category', 'Custom');
    }

    public function gradientClass(): string
    {
        return (string) $this->m('gradientClass', 'from-slate-400 to-slate-600');
    }

    public function accentColor(): string
    {
        return (string) $this->m('accentColor', '#6366f1');
    }

    public function author(): string
    {
        return (string) $this->m('author', 'Imported');
    }

    public function version(): string
    {
        return (string) $this->m('version', '1.0.0');
    }

    public function createdAt(): string
    {
        return (string) $this->m('createdAt', '2026-01-01');
    }

    public function tags(): array
    {
        return (array) $this->m('tags', []);
    }

    public function features(): array
    {
        return (array) $this->m('features', []);
    }

    public function pages(): array
    {
        return $this->pages;
    }

    /** Theme values (consumed by TemplateService::themeOf via method_exists). */
    public function theme(): array
    {
        return $this->theme;
    }

    /** Screenshot URL, or null to fall back to the gradient. */
    public function thumbnail(): ?string
    {
        return $this->thumbnailUrl;
    }
}
