<?php

use App\Models\Site;
use App\Models\User;
use App\Services\CollectionSourceExtractor;
use App\Services\FontLocalizer;
use App\Services\SiteConnect\AssetImporter;
use App\Services\TemplateLint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function fixtureApp(): string
{
    return base_path('tests/fixtures/lint-template');
}

function fidelityFindings(): array
{
    $manifest = ['pages' => [['blocks' => [['name' => 'Hero', 'nodes' => []]]], ['blocks' => []]], 'theme' => ['color-primary' => '#123456', 'font-body' => 'Inter']];

    return app(TemplateLint::class)->analyze($manifest, fixtureApp())['findings'];
}

function findingsIn(array $findings, string $level, string $needle): array
{
    return array_values(array_filter($findings, fn ($f) => $f['level'] === $level && str_contains($f['message'], $needle)));
}

// ── §1 source-aware lint ──

it('flags slot content as an error', function () {
    expect(findingsIn(fidelityFindings(), 'error', 'slot.vue: <PageHero> receives slot content'))->not->toBeEmpty();
});

it('flags inline page markup as an error', function () {
    expect(findingsIn(fidelityFindings(), 'error', 'inline.vue: inline <section> markup'))->not->toBeEmpty();
});

it('flags literal content props as a warning', function () {
    expect(findingsIn(fidelityFindings(), 'warning', 'props.vue: 2 literal content prop(s)'))->not->toBeEmpty();
});

it('flags root-absolute refs to missing public dirs as errors', function () {
    $findings = fidelityFindings();
    // /fonts/ and /videos/ are referenced but neither exists under public/
    expect(findingsIn($findings, 'error', "public/fonts doesn't exist"))->not->toBeEmpty()
        ->and(findingsIn($findings, 'error', "public/videos doesn't exist"))->not->toBeEmpty();
});

it('downgrades asset refs to info when the public dir exists', function () {
    File::ensureDirectoryExists(fixtureApp().'/public/fonts');
    try {
        expect(findingsIn(fidelityFindings(), 'info', '/fonts/ refs found'))->not->toBeEmpty();
    } finally {
        File::deleteDirectory(fixtureApp().'/public/fonts');
    }
});

it('notes google-cdn fonts and unmarked data-source arrays as info', function () {
    $findings = fidelityFindings();
    expect(findingsIn($findings, 'info', 'Google-CDN fonts detected'))->not->toBeEmpty()
        ->and(findingsIn($findings, 'info', 'useGallery.ts: array looks like a media-gallery data source'))->not->toBeEmpty();
});

it('keeps the clean flat page free of fidelity errors', function () {
    $errors = array_filter(fidelityFindings(), fn ($f) => $f['level'] === 'error' && str_contains($f['message'], 'index.vue'));
    expect($errors)->toBeEmpty();
});

// ── §3b collection extraction ──

it('extracts a marked array into a typed collection definition', function () {
    $defs = app(CollectionSourceExtractor::class)->fromSources(fixtureApp());
    $team = collect($defs)->firstWhere('name', 'Team');

    expect($team)->not->toBeNull()
        ->and($team['items'])->toHaveCount(3)
        ->and($team['items'][0]['name'])->toBe('Pastor One');

    $types = collect($team['fields'])->pluck('type', 'key');
    expect($types['img'])->toBe('url')
        ->and($types['joined'])->toBe('date')
        ->and($types['name'])->toBe('text');
});

it('ignores unmarked arrays without a data-olx-source marker', function () {
    $defs = app(CollectionSourceExtractor::class)->fromSources(fixtureApp());
    expect(collect($defs)->firstWhere('name', 'Use Gallery'))->toBeNull();
});

// ── §2 font localization ──

it('self-hosts google fonts and rewrites the nuxt config', function () {
    $dir = fixtureApp().'-fonts-tmp';
    File::deleteDirectory($dir);
    File::copyDirectory(fixtureApp(), $dir);

    Http::fake([
        'fonts.googleapis.com/*' => Http::response(
            "/* latin */\n@font-face {\n  font-family: 'Fraunces';\n  font-style: normal;\n  font-weight: 600;\n  src: url(https://fonts.gstatic.com/s/fraunces/x.woff2) format('woff2');\n}\n"
            ."/* latin */\n@font-face {\n  font-family: 'Inter';\n  font-style: normal;\n  font-weight: 400;\n  src: url(https://fonts.gstatic.com/s/inter/y.woff2) format('woff2');\n}\n"
        ),
        'fonts.gstatic.com/*' => Http::response('WOFF2DATA'),
    ]);

    try {
        $result = app(FontLocalizer::class)->localize($dir);

        expect($result['localized'])->toBeTrue()
            ->and($result['families'])->toContain('Fraunces', 'Inter')
            ->and(File::exists("$dir/public/assets/fonts/fraunces-normal-600.woff2"))->toBeTrue()
            ->and(File::get("$dir/public/assets/fonts/fonts.css"))->toContain("font-family: 'Fraunces'");

        $config = File::get("$dir/nuxt.config.ts");
        expect($config)->toContain("href: '/assets/fonts/fonts.css'")
            ->not->toContain('fonts.googleapis.com')
            ->not->toContain('preconnect');
    } finally {
        File::deleteDirectory($dir);
    }
});

it('keeps the cdn links untouched when the download fails', function () {
    $dir = fixtureApp().'-fonts-fail-tmp';
    File::deleteDirectory($dir);
    File::copyDirectory(fixtureApp(), $dir);

    Http::fake(['fonts.googleapis.com/*' => Http::response('nope', 500)]);

    try {
        $result = app(FontLocalizer::class)->localize($dir);

        expect($result['localized'])->toBeFalse()
            ->and($result['warnings'])->not->toBeEmpty()
            ->and(File::get("$dir/nuxt.config.ts"))->toContain('fonts.googleapis.com/css2');
    } finally {
        File::deleteDirectory($dir);
    }
});

// ── §3 asset importer: new types, labels, caps ──

it('imports fonts, video and audio with pretty labels', function () {
    $site = Site::factory()->for(User::factory())->create();
    $dir = fixtureApp().'/public/assets/gallery';
    File::ensureDirectoryExists($dir);
    File::put("$dir/pastor-two.woff2", str_repeat('f', 128));
    File::put("$dir/sunday-recap.mp4", str_repeat('v', 128));

    try {
        $importer = app(AssetImporter::class);
        expect($importer->importLocal($site, "$dir/pastor-two.woff2"))->not->toBeNull()
            ->and($importer->importLocal($site, "$dir/sunday-recap.mp4"))->not->toBeNull();

        $labels = $site->media()->pluck('name');
        expect($labels)->toContain('Gallery · Pastor Two', 'Gallery · Sunday Recap');
    } finally {
        File::deleteDirectory($dir);
    }
});

it('rejects unknown extensions and oversize files', function () {
    $site = Site::factory()->for(User::factory())->create();
    $dir = fixtureApp().'/public/assets/junk';
    File::ensureDirectoryExists($dir);
    File::put("$dir/script.exe", 'MZ');
    File::put("$dir/huge.mp3", str_repeat('a', 41 * 1024 * 1024));

    try {
        $importer = app(AssetImporter::class);
        expect($importer->importLocal($site, "$dir/script.exe"))->toBeNull()
            ->and($importer->importLocal($site, "$dir/huge.mp3"))->toBeNull();
    } finally {
        File::deleteDirectory($dir);
    }
});

// ── forms extraction ──

it('extracts authored form markup into a CMS form definition', function () {
    $forms = app(\App\Services\FormSourceExtractor::class)->fromSources(fixtureApp());
    $form = collect($forms)->firstWhere('name', 'welcome-signup');

    expect($form)->not->toBeNull()
        ->and($form['title'])->toBe('Welcome Signup');

    $byKey = collect($form['fields'])->keyBy('key');
    expect($byKey['name']['required'])->toBeTrue()
        ->and($byKey['email']['type'])->toBe('email')
        ->and($byKey['message']['type'])->toBe('textarea')
        ->and($byKey->has('submit'))->toBeFalse();
});
