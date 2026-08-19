<?php

use App\Models\IngestedSection;
use App\Services\SiteConnect\ContentClassifier;
use App\Services\SiteConnect\HtmlSanitizer;
use App\Services\SiteConnect\SsrfGuard;
use App\Services\SiteConnect\ThemeExtractor;
use Symfony\Component\DomCrawler\Crawler;

function classify(string $html, string $url = ''): array
{
    return app(ContentClassifier::class)->classify(new Crawler('<div>'.$html.'</div>'), $url);
}

test('a section with a form classifies as form with its fields', function () {
    $r = classify('<form><label>Email</label><input name="email" type="email" required><textarea name="message"></textarea><button>Send</button></form>');

    expect($r['classification'])->toBe(IngestedSection::FORM)
        ->and($r['confidence'])->toBeGreaterThan(0.9)
        ->and($r['fields']['fields'])->toHaveCount(2)
        ->and($r['fields']['fields'][0]['name'])->toBe('email')
        ->and($r['fields']['fields'][0]['required'])->toBeTrue()
        ->and($r['fields']['intent'])->toBe('contact');
});

test('3+ uniform sibling cards classify as a collection with an inferred schema', function () {
    $card = '<div class="card"><h3>%s</h3><p>Desc</p><img src="/a.jpg"><span>£%d</span></div>';
    $html = '<div class="grid">'.sprintf($card, 'Cut', 38).sprintf($card, 'Colour', 60).sprintf($card, 'Style', 25).'</div>';
    $r = classify($html);

    expect($r['classification'])->toBe(IngestedSection::COLLECTION)
        ->and($r['confidence'])->toBeGreaterThanOrEqual(0.7)
        ->and($r['fields']['items'])->toHaveCount(3)
        ->and($r['fields']['schema'])->toContain('title')->toContain('price')
        ->and($r['fields']['items'][0]['title'])->toBe('Cut');
});

test('a two-item array (repeated siblings) classifies as a collection', function () {
    $card = '<article class="c"><h3>%s</h3><p>x</p></article>';
    $r = classify('<div>'.sprintf($card, 'One').sprintf($card, 'Two').'</div>');

    expect($r['classification'])->toBe(IngestedSection::COLLECTION)
        ->and($r['fields']['items'])->toHaveCount(2)
        ->and($r['fields']['items'][0]['title'])->toBe('One');
});

test('a <ul> list classifies as a collection with link items', function () {
    $r = classify('<nav><ul><li><a href="/a">About</a></li><li><a href="/s">Services</a></li></ul></nav>');

    expect($r['classification'])->toBe(IngestedSection::COLLECTION)
        ->and($r['fields']['items'])->toHaveCount(2)
        ->and($r['fields']['items'][0]['title'])->toBe('About')
        ->and($r['fields']['items'][0]['link'])->toBe('/a');
});

test('a form reads field labels from <label for> and builds fields from children', function () {
    $r = classify('<form><label for="e">Your email</label><input id="e" name="email" type="email" required></form>');

    expect($r['classification'])->toBe(IngestedSection::FORM)
        ->and($r['fields']['fields'][0]['label'])->toBe('Your email')
        ->and($r['fields']['fields'][0]['name'])->toBe('email');
});

test('an article classifies as a post', function () {
    $r = classify('<article><h1>Summer tips</h1><time datetime="2026-07-01">July</time><p>Body text here.</p></article>', 'https://x.test/blog/summer-tips');

    expect($r['classification'])->toBe(IngestedSection::POST)
        ->and($r['fields']['title'])->toBe('Summer tips')
        ->and($r['confidence'])->toBeGreaterThan(0.7);
});

test('a hero falls back to component with named fields', function () {
    $r = classify('<section><h1>Look your best</h1><p>Every day.</p><img src="/h.jpg"><a href="/book">Book now</a></section>');

    expect($r['classification'])->toBe(IngestedSection::COMPONENT)
        ->and($r['fields']['heading'])->toBe('Look your best')
        ->and($r['fields']['cta'])->toBe(['label' => 'Book now', 'href' => '/book']);
});

test('the sanitiser strips scripts, event handlers and javascript urls', function () {
    $clean = app(HtmlSanitizer::class)->html('<div onclick="steal()"><script>evil()</script><a href="javascript:x()">x</a><p>ok</p></div>');

    expect($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('javascript:')
        ->and($clean)->toContain('<p>ok</p>');
});

test('the SSRF guard blocks off-host, bad schemes and private IPs', function () {
    $guard = app(SsrfGuard::class);

    expect($guard->allows('https://evil.com/x', ['acme.test']))->toBeFalse()
        ->and($guard->allows('file:///etc/passwd', ['acme.test']))->toBeFalse()
        ->and($guard->allows('http://192.168.0.1/', ['192.168.0.1']))->toBeFalse() // private IP
        ->and($guard->allows('http://8.8.8.8/', ['8.8.8.8']))->toBeTrue();          // public + allowed
});

test('the theme extractor derives an accent colour and font', function () {
    $theme = app(ThemeExtractor::class)->extract('body{font-family:"Playfair Display",serif;color:#7c3aed}.a{background:#7c3aed}');

    expect($theme['accent'])->toBe('#7c3aed')
        ->and($theme['font'])->toBe('Playfair Display');
});
