<?php

it('sends guests to the login screen', function () {
    $this->get('/')->assertRedirect('/login');
});
