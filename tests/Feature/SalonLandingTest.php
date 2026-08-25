<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the salon landing page for guests', function () {
    $this->get('/salons')
        ->assertOk()
        ->assertSee('£79', false)
        ->assertSee('barbershops', false)
        ->assertSee('/register', false);
});
