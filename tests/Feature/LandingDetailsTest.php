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
        ->toContain('/fonts/google/fonts.css');                  // self-hosted brand fonts
});

test('brand fonts are self-hosted: the stylesheet and font files exist locally and pages never call Google Fonts', function () {
    $css = public_path('fonts/google/fonts.css');
    expect(is_file($css))->toBeTrue();
    preg_match_all("#url\\('/fonts/google/([^']+\\.woff2)'\\)#", file_get_contents($css), $m);
    expect($m[1])->not->toBeEmpty();
    foreach (array_unique($m[1]) as $file) {
        expect(is_file(public_path('fonts/google/'.$file)))->toBeTrue("missing {$file}");
    }
    foreach (['Madimi One', 'Text Me One', 'Gugi', 'Kodchasan', 'Momo Trust Display', 'MuseoModerno', 'Cantarell', 'Baumans', 'Abel'] as $family) {
        expect(file_get_contents($css))->toContain("font-family: '{$family}'");
    }
    $this->get('/')->assertOk()->assertDontSee('fonts.googleapis.com', false);
    $this->get('/designs')->assertOk()->assertDontSee('fonts.googleapis.com', false);
});
