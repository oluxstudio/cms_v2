<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests see the landing page with pricing and auth links', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Sign in')
        ->assertSee('Most popular')          // pricing rendered from config/plans.php
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false);
});

test('the landing page shows for everyone at the root', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertOk()->assertSee('Open app');
});

test('the app home lives at /select-site behind auth', function () {
    $this->get('/select-site')->assertRedirect('/login');

    $this->actingAs(User::factory()->create());
    $this->get('/select-site')->assertStatus(200);
});
