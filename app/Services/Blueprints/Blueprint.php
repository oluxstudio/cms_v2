<?php

namespace App\Services\Blueprints;

use App\Models\Service;
use App\Models\ServiceResource;
use App\Models\Site;
use App\Services\TemplateScaffolder;
use App\Templates\TemplatePackage;
use Illuminate\Support\Str;

/**
 * A one-click tenant setup driven by config/blueprints/{key}.php: template
 * pages, the commerce suite + per-feature settings, a seeded service menu
 * with staff/resources, and the forms responses route to. Safe to re-apply —
 * existing pages, services and forms are never duplicated.
 */
abstract class Blueprint
{
    public function __construct(protected TemplateScaffolder $scaffolder) {}

    abstract public function key(): string;

    public function config(): array
    {
        return (array) config("blueprints.{$this->key()}", []);
    }

    /** Business types this blueprint serves: type => ['label' => …, 'icon' => …]. */
    public function types(): array
    {
        return (array) ($this->config()['types'] ?? []);
    }

    /** @param  string|null  $template  a template key chosen by the user (defaults to the blueprint's). */
    public function apply(Site $site, ?string $template = null): void
    {
        $config = $this->config();
        $template = $template ?: ($config['template'] ?? null);

        // 1. Template pages (skips pages whose url already exists).
        $package = collect(TemplatePackage::discover())
            ->first(fn (TemplatePackage $p) => $p->key() === $template);
        if ($package) {
            $this->scaffolder->apply($site, $package);
            $site->update(['template' => $template]);
        }

        // 2. Full commerce suite, then the blueprint's own feature settings.
        $site->enableCommerceSuite();
        foreach ((array) ($config['features'] ?? []) as $feature => $settings) {
            $site->enableFeature($feature, (array) $settings);
        }

        // 3. Service menu + staff/resources (skip when the site already has services).
        if (! empty($config['services']) && ! Service::where('site_id', $site->id)->exists()) {
            $staff = collect((array) ($config['staff'] ?? []))->values()->map(fn ($name, $i) => ServiceResource::create([
                'site_id' => $site->id, 'name' => $name, 'capacity' => 1, 'is_active' => true, 'sort' => $i,
            ]));

            foreach ($config['services'] as $i => [$name, $minutes, $priceCents, $depositPct]) {
                $service = Service::create([
                    'site_id' => $site->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'kind' => 'slot',
                    'duration_min' => $minutes,
                    'price_cents' => $priceCents,
                    'currency' => 'gbp',
                    'requires_payment' => $depositPct !== null,
                    'deposit_pct' => $depositPct,
                    'is_active' => true,
                    'sort' => $i,
                ]);
                $service->resources()->sync($staff->pluck('id')->all());
            }
        }

        // 4. Forms (booking responses route to the first one by name).
        foreach ((array) ($config['forms'] ?? []) as $form) {
            $site->forms()->firstOrCreate(['name' => $form['name']], [
                'title' => $form['title'],
                'fields' => $form['fields'],
                'is_active' => true,
            ]);
        }
    }
}
