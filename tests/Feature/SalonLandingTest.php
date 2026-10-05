<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the salon landing page for guests', function () {
    $this->get('/salons')
        ->assertOk()
        ->assertSee('£'.number_format(config('plans.tiers.growth.price_cents') / 100, 0), false)
        ->assertSee('barbershops', false)
        ->assertSee('/start', false);
});
