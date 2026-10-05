<?php

use App\Support\PlanCatalog;

test('the landing page has a detail panel for every public plan and every specialty', function () {
    $html = $this->get('/')->assertOk()->getContent();

    foreach (PlanCatalog::publicTiers()->keys() as $key) {
        expect($html)->toContain('data-open="plan-'.$key.'"')
            ->toContain('id="plan-'.$key.'"');
    }
    foreach (['domain', 'team', 'ai', 'feed', 'email', 'api'] as $key) {
        expect($html)->toContain('data-open="spec-'.$key.'"')->toContain('id="spec-'.$key.'"');
    }
    expect($html)->toContain('← Back to pricing')
        ->toContain('← Back to features')
        ->toContain('Reason to upgrade')                       // the plan's comparison rows
        ->toContain('fonts.googleapis.com/css2?family=Abel');  // brand fonts
});
