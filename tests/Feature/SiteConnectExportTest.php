<?php

use App\Models\PageIngestion;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteConnect\IngestionProcessor;
use App\Services\SiteConnect\SiteExporter;

function exportFixture(): array
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    $ingestion = PageIngestion::create([
        'site_id' => $site->id, 'source_url' => 'https://acme.test/', 'meta' => ['title' => 'Home'],
        'styles' => 'body{color:#111}',
        'raw_html' => '<body><main>'.
            '<section><h1>Welcome</h1><p>Hi there</p><img src="/h.jpg"></section>'.
            '<div class="grid"><div class="card"><h3>A</h3><span>£1</span></div><div class="card"><h3>B</h3><span>£2</span></div><div class="card"><h3>C</h3><span>£3</span></div></div>'.
            '</main></body>',
        'status' => 'received',
    ]);
    $proc = app(IngestionProcessor::class);
    $proc->process($ingestion);
    // Commit everything (incl. any low-confidence sections) so there's content.
    $proc->commit($ingestion->fresh(), includeReview: true);

    return [$user, $site, $ingestion->fresh()];
}

test('the export bakes attributed, content-filled HTML with the hydrate script', function () {
    [$user, $site] = exportFixture();

    $result = app(SiteExporter::class)->export($site);
    expect($result['pages'])->toBe(1);

    $zip = new ZipArchive;
    $zip->open($result['path']);
    $html = $zip->getFromName('index.html');
    $readme = $zip->getFromName('README.md');
    $zip->close();

    expect($html)->toContain('data-olx-version="')          // SEO + patch gate
        ->and($html)->toContain('data-olx-id=')              // managed sections
        ->and($html)->toContain('data-olx-field="heading"')  // editable node
        ->and($html)->toContain('Welcome')                   // content baked in
        ->and($html)->toContain('/connect.js')               // hydrate script
        ->and($html)->toContain('data-site-name="'.$site->name.'"')
        ->and($readme)->toContain('Site Connect export');

    @unlink($result['path']);
});

test('a committed collection is annotated with a repeating item template', function () {
    [$user, $site] = exportFixture();
    $result = app(SiteExporter::class)->export($site);

    $zip = new ZipArchive;
    $zip->open($result['path']);
    $html = $zip->getFromName('index.html');
    $zip->close();

    expect($html)->toContain('data-olx-item')
        ->and($html)->toContain('data-olx-field="title"');

    @unlink($result['path']);
});

test('the export endpoint is publish-gated and downloads a zip', function () {
    [$user, $site] = exportFixture();

    $this->actingAs($user)
        ->get(route('site.connect.export', ['siteID' => $site->name]))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename='.$site->name.'-site-connect.zip');
});

test('exporting a site with nothing committed 404s', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('site.connect.export', ['siteID' => $site->name]))
        ->assertNotFound();
});
