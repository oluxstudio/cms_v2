<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

test('authenticated users can visit the home screen', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertStatus(200);
});
