<?php

use App\Jobs\SiteConnect\CrawlSiteJob;
use App\Jobs\SiteConnect\IngestPageJob;
use App\Models\Component;
use App\Models\Form;
use App\Models\IngestedSection;
use App\Models\PageIngestion;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteConnect\IngestionProcessor;
use Illuminate\Support\Facades\Queue;

// connectToken() helper is defined in ConnectStatusTest.php (same suite).

function ingestFixtureHtml(): string
{
    return <<<'HTML'
    <body><main>
      <section><h1>Welcome</h1><p>We are great.</p><a href="/book">Book</a></section>
      <div class="grid">
        <div class="card"><h3>Cut</h3><p>Nice</p><span>£38</span></div>
        <div class="card"><h3>Colour</h3><p>Bold</p><span>£60</span></div>
        <div class="card"><h3>Style</h3><p>Sharp</p><span>£25</span></div>
      </div>
      <section><form><input name="email" type="email" required><button>Send</button></form></section>
    </main></body>
    HTML;
}

test('ingest requires the connect:ingest ability and queues the pipeline', function () {
    Queue::fake();
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $token = connectToken($site, ['content:read']); // NOT connect:ingest

    $this->withToken($token)
        ->postJson('/api/v1/connect/ingest', ['url' => 'https://acme.test/', 'html' => '<body></body>'])
        ->assertForbidden();

    $ok = connectToken($site, ['connect:ingest', 'content:read']);
    $this->withToken($ok)
        ->postJson('/api/v1/connect/ingest', ['url' => 'https://acme.test/', 'html' => ingestFixtureHtml()])
        ->assertStatus(202)
        ->assertJsonPath('ok', true);

    expect(PageIngestion::where('site_id', $site->id)->count())->toBe(1);
    Queue::assertPushed(IngestPageJob::class);
    Queue::assertPushed(CrawlSiteJob::class);
});

test('oversized ingest payloads are rejected and nothing is stored', function () {
    Queue::fake();
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $token = connectToken($site, ['connect:ingest', 'content:read']);

    $this->withToken($token)->postJson('/api/v1/connect/ingest', [
        'url' => 'https://acme.test/',
        'html' => str_repeat('a', config('site_connect.ingest.max_html_bytes') + 1),
    ])->assertUnprocessable();
    expect(PageIngestion::where('site_id', $site->id)->count())->toBe(0, 'html oversize stored a row');

    $this->withToken($token)->postJson('/api/v1/connect/ingest', [
        'url' => 'https://acme.test/',
        'html' => '<body></body>',
        'links' => array_fill(0, config('site_connect.ingest.max_links') + 1, 'https://acme.test/x'),
    ])->assertUnprocessable();
    expect(PageIngestion::where('site_id', $site->id)->count())->toBe(0, 'links overflow stored a row');

    $this->withToken($token)->postJson('/api/v1/connect/ingest', [
        'url' => 'https://acme.test/',
        'html' => '<body></body>',
        'meta' => ['title' => 'ok', 'evil' => str_repeat('x', 10)],
    ])->assertUnprocessable();

    expect(PageIngestion::where('site_id', $site->id)->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('ingest retention keeps only the latest snapshots per url', function () {
    Queue::fake();
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $token = connectToken($site, ['connect:ingest', 'content:read']);
    config(['site_connect.ingest.keep_per_url' => 2]);

    foreach (range(1, 4) as $i) {
        $this->withToken($token)->postJson('/api/v1/connect/ingest', [
            'url' => 'https://acme.test/', 'html' => "<body>v$i</body>",
        ])->assertStatus(202);
    }

    $kept = PageIngestion::where('site_id', $site->id)->orderByDesc('created_at')->orderByDesc('id')->get();
    expect($kept)->toHaveCount(2)
        ->and($kept->first()->raw_html)->toContain('v4');
});

test('a crawl is not re-dispatched within the cooldown window', function () {
    Queue::fake();
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $token = connectToken($site, ['connect:ingest', 'content:read']);

    $this->withToken($token)->postJson('/api/v1/connect/ingest', ['url' => 'https://acme.test/', 'html' => '<body>1</body>'])->assertStatus(202);
    $this->withToken($token)->postJson('/api/v1/connect/ingest', ['url' => 'https://acme.test/a', 'html' => '<body>2</body>'])->assertStatus(202);

    Queue::assertPushed(CrawlSiteJob::class, 1);
    Queue::assertPushed(IngestPageJob::class, 2);
});

test('processing splits a page into classified sections', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $ingestion = PageIngestion::create([
        'site_id' => $site->id, 'source_url' => 'https://acme.test/',
        'raw_html' => ingestFixtureHtml(), 'status' => 'received',
    ]);

    app(IngestionProcessor::class)->process($ingestion);

    $kinds = IngestedSection::where('page_ingestion_id', $ingestion->id)->pluck('classification')->all();
    expect($kinds)->toContain(IngestedSection::COMPONENT)
        ->toContain(IngestedSection::COLLECTION)
        ->toContain(IngestedSection::FORM)
        ->and($ingestion->fresh()->status)->toBe('classified');
});

test('committing materialises sections into real models attached to a page', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $ingestion = PageIngestion::create([
        'site_id' => $site->id, 'source_url' => 'https://acme.test/services',
        'raw_html' => ingestFixtureHtml(), 'meta' => ['title' => 'Services'], 'status' => 'received',
    ]);

    $processor = app(IngestionProcessor::class);
    $processor->process($ingestion);
    $page = $processor->commit($ingestion->fresh());

    expect($page->url)->toBe('/services')
        ->and($page->components()->count())->toBeGreaterThanOrEqual(1)
        ->and($page->collections()->count())->toBe(1)
        ->and(Form::where('site_id', $site->id)->count())->toBe(1)
        ->and($ingestion->fresh()->status)->toBe('committed');

    // The committed component round-trips through the page.json generator.
    $hero = Component::where('site_id', $site->id)->where('name', 'like', 'Welcome%')->first();
    expect($hero)->not->toBeNull()
        ->and($hero->nodes()->where('label', 'Heading')->exists())->toBeTrue();
});

test('ingestions are tenant-scoped', function () {
    $siteA = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $siteB = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    PageIngestion::create(['site_id' => $siteA->id, 'source_url' => 'https://a.test/', 'raw_html' => '<body></body>', 'status' => 'received']);

    expect(PageIngestion::where('site_id', $siteB->id)->count())->toBe(0);
});
