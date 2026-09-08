<?php

use App\Jobs\InstallTemplateJob;
use App\Livewire\ConnectReviewPage;
use App\Livewire\SiteTemplatesPage;
use App\Models\Media;
use App\Models\Node;
use App\Models\Site;
use App\Models\User;
use App\Services\TemplateInstaller;
use App\Support\CuratedTemplates;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function pipelineSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'pipe-'.uniqid(), 'domain' => 'pipe-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site];
}

test('applying a template enables the modules, creates its forms, and applies its theme', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $row = $installer->saveCuratedToSite($site, 'hairco');
    $installer->apply($site, $row);
    $site->refresh();

    // Modules: every commerce page opens for the owner.
    foreach (['bookings', 'store', 'orders', 'estimates', 'invoices'] as $seg) {
        $this->actingAs($owner)->get("/{$site->name}/{$seg}")->assertOk();
    }

    // Forms from the manifest.
    expect($site->forms()->pluck('name')->all())->toContain('appointment', 'contact');

    // Theme = the package's tokens, previous stashed for restore.
    expect($site->theme)->not->toBeNull()
        ->and($row->fresh()->previous_theme === null || is_array($row->fresh()->previous_theme))->toBeTrue();

    // Re-apply: no duplicate forms, page count stable.
    $pages = $site->pages()->count();
    $installer->apply($site->fresh(), $row->fresh());
    expect($site->forms()->where('name', 'contact')->count())->toBe(1)
        ->and($site->fresh()->pages()->count())->toBe($pages);
});

test('a public submission to the template-created form lands as a response the owner can read', function () {
    Mail::fake();
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    $this->postJson("/api/sites/{$site->name}/form/contact", ['name' => 'Vis Itor', 'email' => 'vis@example.com', 'message' => 'Hello!'])
        ->assertCreated();

    $form = $site->forms()->where('name', 'contact')->first();
    expect($form->responses()->count())->toBe(1);
    $this->actingAs($owner)->get("/{$site->name}/forms")->assertOk();
});

test('switching designs swaps the theme; stop using restores the original', function () {
    [$owner, $site] = pipelineSite();
    $site->update(['theme' => ['accent' => '#123456']]);
    $installer = app(TemplateInstaller::class);

    $hairco = $installer->saveCuratedToSite($site, 'hairco');
    $installer->apply($site->fresh(), $hairco);
    $haircoTheme = $site->fresh()->theme;
    expect($haircoTheme)->not->toBe(['accent' => '#123456'])
        ->and($hairco->fresh()->previous_theme)->toBe(['accent' => '#123456']);

    $verita = $installer->saveCuratedToSite($site->fresh(), 'verita');
    $installer->apply($site->fresh(), $verita);
    expect($site->fresh()->theme)->not->toBe($haircoTheme)
        ->and($verita->fresh()->previous_theme)->toBe($haircoTheme);

    Livewire\Livewire::actingAs($owner)->test(SiteTemplatesPage::class, ['site' => $site->fresh()])
        ->call('stopUsing');
    expect($site->fresh()->template)->toBe('blank')
        ->and($site->fresh()->theme)->toBe($haircoTheme); // verita's stash = the theme hairco had set
});

test('the connect page embeds the applied template renderer when no client URL is set', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    $this->actingAs($owner)->get("/{$site->name}/connect")
        ->assertOk()->assertSee('nuxt-preview/hairco', false);

    $site->setAttr('client_url', 'https://client.example.com');
    $this->actingAs($owner)->get("/{$site->name}/connect")
        ->assertOk()->assertSee('client.example.com')->assertSee('olx-edit=1', false);
});

test('applyAsync binds instantly and queues the heavy install as a background job', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $row = $installer->saveCuratedToSite($site, 'hairco');

    $site->setAttr('client_url', 'http://localhost:3003');
    Queue::fake();
    $installer->applyAsync($site, $row);

    // A stored client URL is stashed so /connect shows the applied design.
    expect($site->getAttr('client_url'))->toBe('')
        ->and($site->getAttr('client_url_previous'))->toBe('http://localhost:3003');

    // The cheap bind happened in-request…
    expect($site->fresh()->template)->toBe('hairco')
        ->and($row->fresh()->isApplied())->toBeTrue()
        ->and($site->getAttr('template_install'))->toBe('installing');
    // …and no pages yet: that's the job's work.
    expect($site->pages()->count())->toBe(0);
    Queue::assertPushed(InstallTemplateJob::class, fn ($job) => $job->siteId === $site->id && $job->siteTemplateId === $row->id);

    // Running the job completes the install.
    (new InstallTemplateJob($site->id, $row->id))->handle($installer);
    expect($site->fresh()->pages()->count())->toBeGreaterThan(0)
        ->and($site->forms()->pluck('name')->all())->toContain('appointment', 'contact')
        ->and($site->getAttr('template_install'))->toBe('done');
});

test('the connect page shows the setting-up state while the install runs, then the preview', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $row = $installer->saveCuratedToSite($site, 'hairco');

    Queue::fake();
    $installer->applyAsync($site, $row);

    $this->actingAs($owner)->get("/{$site->name}/connect")
        ->assertOk()->assertSee('Setting up your site');

    (new InstallTemplateJob($site->id, $row->id))->handle($installer);
    $this->actingAs($owner)->get("/{$site->name}/connect")
        ->assertOk()->assertDontSee('Setting up your site')->assertSee('nuxt-preview/hairco', false);

    // A failed install offers a retry that re-queues the job.
    $site->setAttr('template_install', 'failed');
    $this->actingAs($owner)->get("/{$site->name}/connect")
        ->assertOk()->assertSee('Try again');
    Livewire\Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site->fresh()])
        ->call('retryInstall');
    expect($site->getAttr('template_install'))->toBe('installing');
    Queue::assertPushed(InstallTemplateJob::class, 2);
});

test('the content API exposes the page wireframe the renderer apps draw from', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    $pages = $this->getJson("/api/sites/{$site->name}/content")->assertOk()->json('pages');
    $wf = collect($pages)->firstWhere('url', '/')['wireframe'] ?? [];

    expect($wf)->not->toBeEmpty()
        ->and($wf[0]['type'])->toStartWith('app:hairco:')
        ->and(collect($wf)->pluck('type'))->toContain('app:hairco:hero')
        ->and($wf[0]['nodes'])->not->toBeEmpty()
        ->and($wf[0]['nodes'][0])->toHaveKeys(['label', 'type', 'value', 'order']);
});

test('applying a template seeds its booking services and availability', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    expect($site->services()->pluck('name')->all())->toContain('Haircut', 'Colouring')
        ->and($site->services()->where('name', 'Haircut')->first()->price_cents)->toBe(3800)
        ->and($site->hasFeature('bookings'))->toBeTrue();

    $cfg = (array) $site->siteFeatures()->where('key', 'bookings')->value('config');
    expect($cfg['days'] ?? null)->toBe('mon,tue,wed,thu,fri,sat')
        ->and($cfg['slot_minutes'] ?? null)->toBe(30);

    // Idempotent, and owner-set availability survives a re-apply.
    $site->saveFeatureConfig('bookings', array_merge($cfg, ['open_time' => '10:00']));
    $installer->apply($site->fresh(), $site->installedTemplates()->first());
    expect($site->services()->where('name', 'Haircut')->count())->toBe(1)
        ->and(((array) $site->siteFeatures()->where('key', 'bookings')->value('config'))['open_time'])->toBe('10:00');
});

test('renderer-mode connect is edit-enabled: olx-edit URL, trusted own origin, slug key resolution', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    $c = Livewire\Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site->fresh()]);
    expect($c->get('embedUrl'))->toContain('olx-edit=1')
        ->and($c->get('clientOrigin'))->toBe(rtrim(url('/'), '/'));

    // The shell posts the slug block key ("Hero" → hero, "Book CTA" → book-cta).
    $hero = $site->contentComponents()->where('name', 'Hero')->first();
    $c->call('onEditSelect', null, 'hero', 'component');
    expect($c->get('selectedKind'))->toBe('component')
        ->and($c->get('selectedId'))->toBe($hero->id);
});

test('applying a template imports its images as default site media and relinks top-level image nodes', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    $media = Media::where('site_id', $site->id)->pluck('name');
    expect($media)->toContain('hero.svg')
        ->and($media->count())->toBeGreaterThan(3);

    $hero = $site->contentComponents()->where('name', 'Hero')->first()->nodes()->where('type', 'image')->first();
    expect($hero->value)->toStartWith('@media/');

    // Repeatable-row image nodes keep their raw shipped paths.
    $rowImage = Node::whereHas('component', fn ($q) => $q->where('site_id', $site->id))
        ->where('label', 'like', '% 1 Image')->first();
    if ($rowImage) {
        expect($rowImage->value)->toStartWith('/assets/');
    }

    // Re-apply: no duplicate media rows.
    $count = Media::where('site_id', $site->id)->count();
    $installer->apply($site->fresh(), $site->installedTemplates()->first());
    expect(Media::where('site_id', $site->id)->count())->toBe($count);
});

test('inline in-page edits from the shell save to the component node', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    $c = Livewire\Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site->fresh()]);

    // Any block with a root text node (the exact set varies per extraction).
    $target = $site->contentComponents()->with('nodes')->get()
        ->first(fn ($comp) => $comp->nodes->first(fn ($n) => $n->type === 'text' && in_array((string) $n->parent, ['', '0'], true)));
    expect($target)->not->toBeNull();
    $node = $target->nodes->first(fn ($n) => $n->type === 'text' && in_array((string) $n->parent, ['', '0'], true));
    $blockKey = Str::slug($target->name);
    $fieldKey = Str::camel(Str::slug($node->label));

    $c->call('inlineFieldEdit', null, $blockKey, 'component', $fieldKey, 'Fresh inline words');
    expect($target->nodes()->where('label', $node->label)->first()->value)->toBe('Fresh inline words');

    // Editing a marker whose node doesn't exist yet CREATES it (dynamic
    // field() bindings aren't extracted) — the edit must persist.
    $c->call('inlineFieldEdit', null, Str::slug($target->name), 'component', 'brandNewField', 'persisted now');
    expect($target->nodes()->where('label', 'Brand New Field')->first()?->value)->toBe('persisted now');

    // The content API serves the edit to the live preview.
    $pages = $this->getJson("/api/sites/{$site->name}/content")->json('pages');
    $values = collect($pages)->flatMap(fn ($p) => collect($p['wireframe'] ?? [])->flatMap(fn ($b) => collect($b['nodes'])->pluck('value')));
    expect($values)->toContain('Fresh inline words');
});

test('applying a template scaffolds its chrome and every page wireframe carries header→content→footer', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    // Chrome components exist once, site-level, tagged.
    $header = $site->contentComponents()->where('name', 'Site Header')->first();
    $footer = $site->contentComponents()->where('name', 'Site Footer')->first();
    expect($header?->tags)->toContain('chrome:header')
        ->and($footer?->tags)->toContain('chrome:footer');

    // Every page's wireframe: header first, footer last…
    $pages = $this->getJson("/api/sites/{$site->name}/content")->assertOk()->json('pages');
    foreach ($pages as $p) {
        $types = collect($p['wireframe'])->pluck('type');
        expect($types->first())->toBe('app:hairco:site-header')
            ->and($types->last())->toBe('app:hairco:site-footer');
    }

    // …and NOTHING from the manifest home page is missing (completeness).
    $home = collect($pages)->firstWhere('url', '/');
    $manifest = json_decode(file_get_contents(resource_path('templates/hairco/pages/home.json')), true);
    $expected = collect($manifest['blocks'])->pluck('type');
    expect(collect($home['wireframe'])->pluck('type')->all())->toContain(...$expected->all());

    // Re-apply: chrome not duplicated.
    $installer->apply($site->fresh(), $site->installedTemplates()->first());
    expect($site->contentComponents()->where('name', 'Site Header')->count())->toBe(1);
});

test('a block used on several pages is ONE shared component, and image swaps show everywhere', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    // No duplicate component names on the site.
    $names = $site->contentComponents()->pluck('name');
    expect($names->count())->toBe($names->unique()->count());

    // Swap a shared component's image to a site asset → every page that uses
    // the block serves the RESOLVED asset URL.
    $about = $site->contentComponents()->where('name', 'About')->first();
    $imageNode = $about?->nodes()->where('type', 'image')->first();
    if ($imageNode) {
        $imageNode->update(['value' => '@media/work-style.jpg']);
        $pages = $this->getJson("/api/sites/{$site->name}/content")->json('pages');
        foreach ($pages as $p) {
            foreach ($p['wireframe'] as $b) {
                if ($b['name'] === 'About') {
                    expect(collect($b['nodes'])->firstWhere('label', $imageNode->label)['value'])
                        ->toContain('/storage/media/');
                }
            }
        }
        // payload() (components API surface) resolves too.
        expect(collect($about->fresh()->load('nodes')->payload()['nodes'])->firstWhere('label', $imageNode->label)['value'])
            ->toContain('/storage/media/');
    }
});

test('Add item in the preview clones a new row onto a node-based list', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    // Find a component with "{Prefix} 1 {Field}" rows.
    $target = null;
    $prefix = null;
    foreach ($site->contentComponents()->with('nodes')->get() as $comp) {
        foreach ($comp->nodes as $n) {
            if (preg_match('/^(.+?) 1 (.+)$/', (string) $n->label, $m)) {
                [$target, $prefix] = [$comp, $m[1]];
                break 2;
            }
        }
    }
    expect($target)->not->toBeNull();

    $countRow = fn ($i) => $target->nodes()->where('label', 'like', "{$prefix} {$i} %")->count();
    $rowFields = $countRow(1);
    $before = $target->nodes()->count();
    $max = 1;
    while ($countRow($max + 1) > 0) {
        $max++;
    }

    Livewire\Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site->fresh()])
        ->call('inlineNodeItemAdd', Str::slug($target->name), $prefix);

    expect($countRow($max + 1))->toBe($rowFields)
        ->and($target->nodes()->count())->toBe($before + $rowFields);
});

test('applying hairco seeds its collections with items; add/remove items works by marker key', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    $col = $site->collections()->where('slug', 'about-points')->first();
    expect($col)->not->toBeNull()
        ->and($col->is_public)->toBeTrue()
        ->and($col->items()->count())->toBeGreaterThan(2);
    $count = $col->items()->count();

    // Public collections API serves them (the shell's items() source).
    $this->getJson("/api/sites/{$site->name}/collections")->assertOk()
        ->assertJsonFragment(['slug' => 'about-points']);

    // Preview "+ Add item" → one more published row; ✕ by index removes it.
    $c = Livewire\Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site->fresh()]);
    $c->call('inlineItemAdd', null, 'about-points', null, null);
    expect($col->items()->count())->toBe($count + 1);
    $c->call('inlineItemRemoveByIndex', 'about-points', $count); // the new last row
    expect($col->items()->count())->toBe($count);

    // Typing directly into a row in the preview saves to that row.
    $c->call('inlineFieldEditByIndex', 'about-points', 'point', 'Typed in the page', 0);
    expect($col->items()->orderBy('position')->orderBy('id')->first()->data['point'])->toBe('Typed in the page');

    // camelCase marker keys resolve too ("aboutPoints" → about-points).
    $c->call('inlineItemAdd', null, 'aboutPoints', null, null);
    expect($col->items()->count())->toBe($count + 1);
    $c->call('inlineItemRemoveByIndex', 'aboutPoints', $count);
    expect($col->items()->count())->toBe($count);

    // Re-apply never duplicates or reseeds.
    $col->items()->orderBy('position')->first()->delete();
    $installer->apply($site->fresh(), $site->installedTemplates()->first());
    expect($site->collections()->where('slug', 'about-points')->count())->toBe(1)
        ->and($col->items()->count())->toBe($count - 1);
});

test('curated gallery cards carry the real manifest description after the registry path fix', function () {
    $desc = json_decode(file_get_contents(resource_path('templates/verita/template.json')), true)['description'];
    expect(CuratedTemplates::find('verita')['description'])->toBe($desc);
    $this->get('/designs/curated-verita')->assertOk()->assertSee('Verita');
});
