<?php

use App\Models\Service;
use App\Models\ServiceResource;
use App\Models\Site;
use App\Models\User;
use App\Services\Blueprints\SalonBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provisions a full salon site from the blueprint', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);

    app(SalonBlueprint::class)->apply($site);

    // hairco template pages scaffolded (home, about, services, appointment, contact-us).
    expect($site->pages()->count())->toBeGreaterThanOrEqual(5)
        ->and($site->pages()->where('url', '/appointment')->exists())->toBeTrue()
        ->and($site->pages()->where('url', '/')->first()->components()->count())->toBeGreaterThan(0);

    // Bookings feature enabled with salon hours.
    expect($site->hasFeature('bookings'))->toBeTrue()
        ->and($site->feature('bookings')['days'])->toBe('mon,tue,wed,thu,fri,sat')
        ->and($site->feature('bookings')['slot_minutes'])->toBe(30);

    // Service menu + deposit on colour + two chairs on every service.
    $services = Service::where('site_id', $site->id)->get();
    expect($services)->toHaveCount(5);
    $colour = $services->firstWhere('name', 'Colour');
    expect($colour->deposit_pct)->toBe(25)->and((bool) $colour->requires_payment)->toBeTrue();
    expect(ServiceResource::where('site_id', $site->id)->count())->toBe(2)
        ->and($services->first()->resources()->count())->toBe(2);

    // Appointment form for booking responses.
    expect($site->forms()->where('name', 'appointment')->exists())->toBeTrue();
});

it('is safe to re-apply without duplicating content', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $blueprint = app(SalonBlueprint::class);

    $blueprint->apply($site);
    $pages = $site->pages()->count();
    $blueprint->apply($site);

    expect($site->pages()->count())->toBe($pages)
        ->and(Service::where('site_id', $site->id)->count())->toBe(5)
        ->and($site->forms()->where('name', 'appointment')->count())->toBe(1);
});
