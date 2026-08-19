<?php

use App\Services\SiteConnect\HtmlSanitizer;

function sanitize(string $html): string
{
    return app(HtmlSanitizer::class)->html($html);
}

test('scripts, iframes and event handlers are stripped (existing behavior)', function () {
    $out = sanitize('<div onclick="x()"><script>evil()</script><iframe src="//x"></iframe><p>keep</p></div>');
    expect($out)->not->toContain('script')->not->toContain('iframe')->not->toContain('onclick')
        ->toContain('<p>keep</p>');
});

test('base and link tags are stripped', function () {
    $out = sanitize('<base href="https://evil.example/"><link rel="stylesheet" href="https://evil.example/x.css"><p>keep</p>');
    expect($out)->not->toContain('<base')->not->toContain('<link')->toContain('keep');
});

test('meta http-equiv is stripped, charset meta survives', function () {
    $out = sanitize('<meta http-equiv="refresh" content="0;url=https://evil.example"><meta charset="utf-8"><p>k</p>');
    expect($out)->not->toContain('http-equiv')->toContain('charset');
});

test('data: URLs are stripped except data:image', function () {
    $out = sanitize('<a href="data:text/html;base64,PHNjcmlwdD4=">x</a><img src="data:image/png;base64,iVBOR">');
    expect($out)->not->toContain('data:text/html')->toContain('data:image/png');
});

test('javascript: URLs are stripped from srcset too', function () {
    $out = sanitize('<img srcset="javascript:alert(1) 1x"><a href="javascript:alert(1)">x</a>');
    expect($out)->not->toContain('javascript:');
});

test('formtarget and target=_top are stripped', function () {
    $out = sanitize('<button formtarget="_blank">b</button><a target="_top" href="/x">a</a><a target="_blank" href="/y">c</a>');
    expect($out)->not->toContain('formtarget')->not->toContain('_top')->toContain('_blank');
});
