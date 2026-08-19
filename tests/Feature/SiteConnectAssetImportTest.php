<?php

use App\Models\ApiToken;
use App\Models\IngestedSection;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageIngestion;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteConnect\AssetImporter;
use App\Services\SiteConnect\PageJsonGenerator;
use App\Services\SiteConnect\SectionCommitter;
use App\Services\SiteConnect\SsrfGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Test hosts (acme.test) don't resolve, and the guard now rejects empty DNS —
// stub resolution to a public IP so the allow-list logic is what's under test.
beforeEach(function () {
    app()->bind(SsrfGuard::class, fn () => new class extends SsrfGuard
    {
        protected function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
});

function assetImportSection(string $imageUrl): array
{
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $ingestion = PageIngestion::create([
        'site_id' => $site->id, 'source_url' => 'https://acme.test/',
        'raw_html' => '<body></body>', 'status' => 'classified',
    ]);
    $page = Page::factory()->create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/']);
    $section = IngestedSection::create([
        'page_ingestion_id' => $ingestion->id, 'site_id' => $site->id, 'position' => 0,
        'tag' => 'section', 'html' => '<section></section>', 'classification' => IngestedSection::COMPONENT,
        'confidence' => 0.9, 'fields' => ['heading' => 'Hero', 'image' => $imageUrl],
    ]);

    return [$site, $page, $section];
}

test('committing a component imports its image into the media library as an @media ref', function () {
    Storage::fake('public');
    Http::fake(['acme.test/*' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png'])]);
    [$site, $page, $section] = assetImportSection('https://acme.test/img/hero.png');

    app(SectionCommitter::class)->commit($section, $page);

    $media = Media::where('site_id', $site->id)->first();
    expect($media)->not->toBeNull()
        ->and($media->name)->toBe('hero.png')
        ->and($media->file_type)->toBe('image')
        ->and(Storage::disk('public')->exists('media/'.$site->name.'/hero.png'))->toBeTrue();

    $node = $page->components()->first()->nodes()->where('type', 'image')->first();
    expect($node->value)->toBe('@media/hero.png');
});

test('page.json serves the imported asset as an absolute CMS url', function () {
    Storage::fake('public');
    Http::fake(['acme.test/*' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png'])]);
    [$site, $page, $section] = assetImportSection('https://acme.test/img/hero.png');
    app(SectionCommitter::class)->commit($section, $page);

    $doc = app(PageJsonGenerator::class)->generate($page->fresh());
    $hero = collect($doc['componentData'])->firstWhere('key', 'hero');

    expect($hero['fields']['image']['src'])->toBe(url('/storage/media/'.$site->name.'/hero.png'));
});

test('an unreachable asset keeps the original url', function () {
    Storage::fake('public');
    Http::fake(['acme.test/*' => Http::response('', 404)]);
    [$site, $page, $section] = assetImportSection('https://acme.test/img/missing.png');

    app(SectionCommitter::class)->commit($section, $page);

    expect(Media::where('site_id', $site->id)->count())->toBe(0)
        ->and($page->components()->first()->nodes()->where('type', 'image')->first()->value)
        ->toBe('https://acme.test/img/missing.png');
});

test('a host outside the ingestion source is not fetched', function () {
    Storage::fake('public');
    Http::fake();
    [$site, $page, $section] = assetImportSection('https://evil.example/steal.png');

    app(SectionCommitter::class)->commit($section, $page);

    Http::assertNothingSent();
    expect($page->components()->first()->nodes()->where('type', 'image')->first()->value)
        ->toBe('https://evil.example/steal.png');
});

test('re-committing reuses the already imported asset instead of duplicating it', function () {
    Storage::fake('public');
    Http::fake(['acme.test/*' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png'])]);
    [$site, $page, $section] = assetImportSection('https://acme.test/img/hero.png');

    app(SectionCommitter::class)->commit($section, $page);
    app(SectionCommitter::class)->commit($section->fresh(), $page);

    expect(Media::where('site_id', $site->id)->count())->toBe(1);
});

test('an image node saved through the component API is imported into the media library', function () {
    Storage::fake('public');
    Http::fake(['cdn.example/*' => Http::response('img-bytes', 200, ['Content-Type' => 'image/jpeg'])]);
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    $token = ApiToken::create([
        'user_id' => $user->id, 'site_id' => $site->id, 'name' => 't',
        'token' => hash('sha256', $raw = Str::random(60)),
        'token_preview' => 'x', 'abilities' => ['components.manage'],
    ]);

    $this->withToken($raw)->postJson("/api/sites/{$site->name}/components", [
        'name' => 'Gallery',
        'nodes' => [['label' => 'Photo', 'type' => 'image', 'value' => 'https://cdn.example/pic.jpg']],
    ])->assertCreated();

    $node = $site->contentComponents()->first()->nodes()->first();
    expect($node->value)->toBe('@media/pic.jpg')
        ->and(Media::where('site_id', $site->id)->where('name', 'pic.jpg')->exists())->toBeTrue();
});

test('a root-relative image value resolves against the client_url before import', function () {
    Storage::fake('public');
    Http::fake(['client.example/*' => Http::response('img-bytes', 200, ['Content-Type' => 'image/png'])]);
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    $site->setAttr('client_url', 'https://client.example');
    $importer = app(AssetImporter::class);

    expect($importer->importNodeValue($site->fresh(), '/assets/hero.png'))->toBe('@media/hero.png')
        // @media refs and CMS-own paths pass through untouched, no fetch.
        ->and($importer->importNodeValue($site, '@media/hero.png'))->toBe('@media/hero.png')
        ->and($importer->importNodeValue($site, '/storage/media/x/y.png'))->toBe('/storage/media/x/y.png');
});

test('a relative image value with no client_url configured is kept as-is', function () {
    Storage::fake('public');
    Http::fake();
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);

    expect(app(AssetImporter::class)->importNodeValue($site, '/img/x.jpg'))->toBe('/img/x.jpg');
    Http::assertNothingSent();
});
