<?php

it('shows guests the public landing page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Sign in')
        ->assertSee('14-day free trial');
});
