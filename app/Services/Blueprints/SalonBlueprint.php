<?php

namespace App\Services\Blueprints;

use App\Models\Service;
use App\Models\ServiceResource;
use App\Models\Site;
use App\Services\TemplateScaffolder;
use App\Templates\TemplatePackage;
use Illuminate\Support\Str;

/**
 * One-click "Salon & Barber" tenant: hairco template pages, bookings enabled
 * with salon hours, a seeded service menu (with a deposit on colour), two
 * staff chairs and the appointment form. Data lives in config/blueprints/salon.php.
 * Safe to re-apply — existing pages/services are not duplicated.
 */
class SalonBlueprint
{
    public function __construct(private TemplateScaffolder $scaffolder) {}

    public function apply(Site $site): void
    {
        $config = config('blueprints.salon');

        // 1. Template pages (skips pages whose url already exists).
        $package = collect(TemplatePackage::discover())
            ->first(fn (TemplatePackage $p) => $p->key() === $config['template']);
        if ($package) {
            $this->scaffolder->apply($site, $package);
            $site->update(['template' => $config['template']]);
        }

        // 2. Full commerce suite, with bookings configured for salon hours.
        $site->enableCommerceSuite();
        $site->enableFeature('bookings', $config['booking_settings']);

        // 3. Service menu + staff (skip when the site already has services).
        if (! Service::where('site_id', $site->id)->exists()) {
            $staff = collect($config['staff'])->map(fn ($name, $i) => ServiceResource::create([
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

        // 4. Appointment form — booking responses route here by name.
        $form = $config['form'];
        $site->forms()->firstOrCreate(['name' => $form['name']], [
            'title' => $form['title'],
            'fields' => $form['fields'],
            'is_active' => true,
        ]);
    }
}
