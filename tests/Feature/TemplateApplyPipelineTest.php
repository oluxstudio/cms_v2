<?php

use App\Jobs\InstallTemplateJob;
use App\Livewire\ConnectReviewPage;
use App\Livewire\PageComponent;
use App\Livewire\PlatformTemplatesPage;
use App\Livewire\SiteTemplatesPage;
use App\Models\CollectionItem;
use App\Models\Contact;
use App\Models\Media;
use App\Models\Message;
use App\Models\Node;
use App\Models\Post;
use App\Models\Site;
use App\Models\SiteAttribute;
use App\Models\Template;
use App\Models\Todo;
use App\Models\User;
use App\Services\InstallProgress;
use App\Services\TemplateInstaller;
use App\Services\TwoFactor;
use App\Support\CuratedTemplates;
use App\Templates\TemplateAppRegistry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** Mark a first-party template's collections as "reset" (start empty on each site); [] = all of them. */
function resetTemplateCollections(string $key, ?array $names = null): void
{
    $names ??= array_column((array) (TemplateAppRegistry::find($key)['manifest']['collections'] ?? []), 'name');
    publishBuiltinTemplate($key)->update(['reset_collections' => $names]);
}

// Reset settings live on shared catalog rows — clear them so other tests see the defaults.
afterEach(fn () => Template::whereIn('builtin_key', ['hairco', 'v2hairco', 'verita'])->update(['reset_collections' => null]));

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

test('a fresh copy brings booking availability but none of the template\'s sample services', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    expect($site->services()->count())->toBe(0)          // services are data — never copied
        ->and($site->hasFeature('bookings'))->toBeTrue();

    $cfg = (array) $site->siteFeatures()->where('key', 'bookings')->value('config');
    expect($cfg['days'] ?? null)->toBe('mon,tue,wed,thu,fri,sat')
        ->and($cfg['slot_minutes'] ?? null)->toBe(30);

    // Owner-set availability survives a re-apply.
    $site->saveFeatureConfig('bookings', array_merge($cfg, ['open_time' => '10:00']));
    $installer->apply($site->fresh(), $site->installedTemplates()->first());
    expect(((array) $site->siteFeatures()->where('key', 'bookings')->value('config'))['open_time'])->toBe('10:00')
        ->and($site->services()->count())->toBe(0);
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

test('a fresh copy imports none of the template\'s assets; image fields keep the template\'s own paths', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    expect(Media::where('site_id', $site->id)->count())->toBe(0);

    // Sample images still show — they point at the template's shipped files.
    $hero = $site->contentComponents()->where('name', 'Hero')->first()->nodes()->where('type', 'image')->first();
    expect($hero->value)->not->toStartWith('@media/')->and($hero->value)->not->toBe('');
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
        // A fresh site's Assets library starts empty — add the picture the owner swaps in.
        Media::create(['site_id' => $site->id, 'name' => 'work-style.jpg', 'file_type' => 'image', 'url' => '/storage/media/'.$site->name.'/work-style.jpg']);
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

test('a RESET collection starts empty, the others carry the template\'s entries; adding/removing entries works by marker key', function () {
    resetTemplateCollections('hairco', ['About Points']);
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    $col = $site->collections()->where('slug', 'about-points')->first();
    expect($col)->not->toBeNull()
        ->and($col->is_public)->toBeTrue()
        ->and($col->fields)->not->toBeEmpty()          // the structure…
        ->and($col->items()->count())->toBe(0);        // …reset: none of the template's entries

    // Not reset → loads as in the template.
    $templateServices = collect(TemplateAppRegistry::find('hairco')['manifest']['collections'])->firstWhere('name', 'Services');
    expect($site->collections()->where('slug', 'services')->first()->items()->count())->toBe(count($templateServices['items']));

    // Public collections API serves it (the shell's items() source → falls back to the template samples).
    $this->getJson("/api/sites/{$site->name}/collections")->assertOk()
        ->assertJsonFragment(['slug' => 'about-points']);

    // Preview "+ Add item" → a row; typing saves to it; ✕ removes it.
    $c = Livewire\Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site->fresh()]);
    $c->call('inlineItemAdd', null, 'about-points', null, null);
    expect($col->items()->count())->toBe(1);
    $c->call('inlineFieldEditByIndex', 'about-points', 'point', 'Typed in the page', 0);
    expect($col->items()->first()->data['point'])->toBe('Typed in the page');
    $c->call('inlineItemRemoveByIndex', 'aboutPoints', 0);     // camelCase marker keys resolve too
    expect($col->items()->count())->toBe(0);

    // Re-apply never duplicates the collection or seeds entries.
    $installer->apply($site->fresh(), $site->installedTemplates()->first());
    expect($site->collections()->where('slug', 'about-points')->count())->toBe(1)
        ->and($col->items()->count())->toBe(0);
});

test('curated gallery cards carry the real manifest description after the registry path fix', function () {
    $desc = json_decode(file_get_contents(resource_path('templates/verita/template.json')), true)['description'];
    expect(CuratedTemplates::find('verita')['description'])->toBe($desc);
    publishBuiltinTemplate('verita');   // public only through its published catalog row
    $this->get('/designs/curated-verita')->assertOk()->assertSee('Verita');
});

test('a fresh copy keeps pages, sections, layout, theme and form definitions — and none of the template\'s data', function () {
    resetTemplateCollections('hairco');   // every collection reset → no template entries at all
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));
    $site = $site->fresh();

    // Kept: the structure and sample text.
    expect($site->livePages()->count())->toBeGreaterThan(1)
        ->and($site->contentComponents()->whereHas('nodes')->count())->toBeGreaterThan(3)
        ->and($site->contentComponents()->where('name', 'Site Header')->exists())->toBeTrue()
        ->and($site->forms()->count())->toBeGreaterThan(0); // definitions only (submissions are contacts — none, checked below)

    // Not copied: entries, products, services, assets, posts, contacts, messages, template tasks.
    expect(CollectionItem::where('site_id', $site->id)->count())->toBe(0)
        ->and($site->products()->count())->toBe(0)
        ->and($site->services()->count())->toBe(0)
        ->and(Media::where('site_id', $site->id)->count())->toBe(0)
        ->and(Post::where('site_id', $site->id)->count())->toBe(0)
        ->and(Contact::where('site_id', $site->id)->count())->toBe(0)
        ->and(Message::where('site_id', $site->id)->count())->toBe(0)
        // only the platform's own "Set up your site" checklist
        ->and(Todo::where('site_id', $site->id)->whereNull('system_key')->count())->toBe(0);
});

test('installing reports progress step by step, and the setup screen shows a progress bar', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $row = $installer->saveCuratedToSite($site, 'hairco');

    // While the job is queued: "installing" with a starting progress.
    Queue::fake();
    $installer->applyAsync($site, $row);
    expect(InstallProgress::read($site->fresh()))->toMatchArray(['percent' => 1, 'step' => 'start']);
    Livewire\Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site->fresh()])
        ->assertSee('Setting up your site')->assertSee('Pages & sections')->assertSeeHtml('wire:poll.1s');

    // Each step is recorded; the end is 100%.
    $seen = [];
    $site->setAttr(InstallProgress::ATTR, '');
    SiteAttribute::saved(function ($attr) use (&$seen) {
        if ($attr->key === InstallProgress::ATTR && ($p = json_decode((string) $attr->value, true))) {
            $seen[] = $p['step'];
        }
    });
    $installer->install($site->fresh(), $row->fresh());
    expect(array_values(array_unique($seen)))->toContain('pages', 'layout', 'forms', 'collections', 'theme', 'done')
        ->and(InstallProgress::read($site->fresh())['percent'])->toBe(100)
        ->and($site->fresh()->getAttr('template_install'))->toBe('done');
});

test('switching templates imports no template data or assets; the site keeps its own data and the new template uses it', function () {
    resetTemplateCollections('hairco');
    resetTemplateCollections('v2hairco');
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    // The owner's own data, built up while on hairco.
    $services = $site->collections()->where('slug', 'services')->firstOrFail();
    $services->items()->create(['site_id' => $site->id, 'data' => ['title' => 'Our own cut'], 'status' => 'published']);
    $nav = $site->collections()->where('slug', 'navigation')->firstOrFail();
    $nav->items()->create(['site_id' => $site->id, 'data' => ['label' => 'Home'], 'status' => 'published']);
    Media::create(['site_id' => $site->id, 'name' => 'mine.jpg', 'file_type' => 'image', 'url' => '/storage/media/mine.jpg', 'size' => '1 KB', 'bytes' => 1024]);
    $before = [
        'items' => CollectionItem::where('site_id', $site->id)->count(),
        'media' => Media::where('site_id', $site->id)->count(),
        'posts' => Post::where('site_id', $site->id)->count(),
        'contacts' => Contact::where('site_id', $site->id)->count(),
    ];

    // Switch to v2hairco (shares Services/Team/… with hairco; adds Footer Links etc.).
    $installer->apply($site->fresh(), $installer->saveCuratedToSite($site->fresh(), 'v2hairco'));
    $site->refresh();

    // Nothing of the template's data or assets came in…
    expect(CollectionItem::where('site_id', $site->id)->count())->toBe($before['items'])
        ->and(Media::where('site_id', $site->id)->count())->toBe($before['media'])
        ->and(Post::where('site_id', $site->id)->count())->toBe($before['posts'])
        ->and(Contact::where('site_id', $site->id)->count())->toBe($before['contacts']);

    // …the site's own collections and entries are untouched and reused (no duplicates)…
    expect($site->collections()->where('slug', 'services')->count())->toBe(1)
        ->and($services->items()->pluck('data')->pluck('title')->all())->toBe(['Our own cut'])
        ->and($nav->fresh())->not->toBeNull()                              // a collection the new template doesn't use stays
        ->and($nav->items()->count())->toBe(1);

    // …collections only the new template has start empty…
    $footer = $site->collections()->where('slug', 'footer-links')->first();
    expect($footer)->not->toBeNull()->and($footer->items()->count())->toBe(0);

    // …and the new template reads the site's data: every collection is served by slug.
    $api = collect($this->getJson("/api/sites/{$site->name}/collections")->assertOk()->json('collections'))->keyBy('slug');
    expect(json_encode($api->get('services')))->toContain('Our own cut')
        ->and($api->has('navigation'))->toBeTrue();
});

test('a switch never edits what the site already has: sections, forms and collections stay exactly as the owner left them', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));

    // The owner trims and edits things the next template also declares.
    $hero = $site->contentComponents()->where('name', 'Hero')->firstOrFail();
    $removed = $hero->nodes()->orderBy('order')->first();
    $removed->delete();
    $heroNodes = $hero->nodes()->orderBy('id')->get(['label', 'value'])->toArray();
    $contact = $site->forms()->where('name', 'contact')->firstOrFail();
    $contact->update(['fields' => [['key' => 'email', 'label' => 'Email', 'type' => 'email']]]);
    $services = $site->collections()->where('slug', 'services')->firstOrFail();
    $services->update(['fields' => [['key' => 'title', 'label' => 'Name', 'type' => 'text']]]);
    $componentIds = $site->contentComponents()->pluck('id')->sort()->values()->all();

    $installer->apply($site->fresh(), $installer->saveCuratedToSite($site->fresh(), 'v2hairco'));

    expect($hero->nodes()->orderBy('id')->get(['label', 'value'])->toArray())->toBe($heroNodes)   // not topped up
        ->and($hero->nodes()->where('label', $removed->label)->exists())->toBeFalse()
        ->and($contact->fresh()->fields)->toEqual([['key' => 'email', 'label' => 'Email', 'type' => 'email']])   // (JSON key order aside)
        ->and($services->fresh()->fields)->toEqual([['key' => 'title', 'label' => 'Name', 'type' => 'text']])
        ->and($site->contentComponents()->whereIn('id', $componentIds)->count())->toBe(count($componentIds)); // nothing removed

    // A refresh of the SAME template (template:deploy) may top its own sections back up.
    $installer->refreshAppliedSites('v2hairco');
    expect($hero->nodes()->where('label', $removed->label)->exists())->toBeTrue();
});

test('the renderer stub shows a site\'s own rows only — template samples are for the template preview', function () {
    $stub = file_get_contents(base_path('stubs/olux/useCms.ts'));
    expect($stub)->toContain('return isSite ? [] : fallback')
        ->toContain("isSite: !!site && !q.has('template')")
        ->toContain('window as any).__OLUX_SITE__');
});

test('only the current template\'s pages, sections and forms are active; the others are parked, never deleted', function () {
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $installer->apply($site, $installer->saveCuratedToSite($site, 'hairco'));
    $own = $site->pages()->create(['name' => 'My notes', 'url' => '/my-notes', 'keywords' => '', 'is_published' => true]);
    $pageCount = $site->pages()->count();
    $formCount = $site->forms()->count();

    $installer->apply($site->fresh(), $installer->saveCuratedToSite($site->fresh(), 'verita'));
    $site->refresh();
    $page = fn ($url) => $site->pages()->where('url', $url)->first();

    // Nothing deleted…
    expect($site->pages()->count())->toBeGreaterThanOrEqual($pageCount)
        ->and($site->forms()->count())->toBeGreaterThanOrEqual($formCount);
    // …hairco-only pages parked, shared + verita pages and the owner's own page active.
    expect($page('/shop')->template_active)->toBeFalse()
        ->and($page('/services')->template_active)->toBeFalse()
        ->and($page('/')->template_active)->toBeTrue()
        ->and($page('/contact')->template_active)->toBeTrue()
        ->and($own->fresh()->template_active)->toBeTrue()
        ->and($own->fresh()->template_keys)->toBeNull();
    // Forms: verita's contact form stays; hairco-only forms are parked and refuse submissions.
    expect($site->forms()->where('name', 'contact')->value('template_active'))->toBeTrue()
        ->and($site->forms()->where('name', 'appointment')->value('template_active'))->toBeFalse();
    $this->postJson("/api/sites/{$site->name}/form/appointment", ['name' => 'X', 'email' => 'x@x.test'])->assertForbidden();

    // Home shows only verita's sections; hairco's are kept on the page but hidden.
    $home = $page('/');
    expect($home->activeComponents()->count())->toBeLessThan($home->components()->count())
        ->and($home->activeComponents()->get()->every(fn ($c) => in_array('verita', json_decode((string) $c->pivot->template_keys, true) ?: [], true)))->toBeTrue();

    // The live site / API never shows parked pages.
    $urls = collect($this->getJson("/api/sites/{$site->name}/content")->assertOk()->json('pages'))->pluck('url');
    expect($urls)->not->toContain('/shop')->toContain('/contact')->toContain('/my-notes');
    $this->getJson("/api/sites/{$site->name}/page?url=/shop")->assertNotFound();

    // Admin lists them, marked inactive, with an Activate button.
    Livewire\Livewire::actingAs($owner)->test(PageComponent::class, ['site' => $site])
        ->assertSee('Inactive — from')
        ->call('activatePage', $page('/shop')->id);
    expect($page('/shop')->fresh()->template_active)->toBeTrue();   // the owner's call: active under any template

    // Switching back brings hairco's pages and forms back and parks verita's.
    $installer->apply($site->fresh(), $installer->saveCuratedToSite($site->fresh(), 'hairco'));
    expect($page('/services')->fresh()->template_active)->toBeTrue()
        ->and($page('/contact')->fresh()->template_active)->toBeFalse()
        ->and($page('/shop')->fresh()->template_active)->toBeTrue()
        ->and($site->forms()->where('name', 'appointment')->value('template_active'))->toBeTrue();

    // Stop using any template → everything is active again.
    Livewire\Livewire::actingAs($owner)->test(SiteTemplatesPage::class, ['site' => $site->fresh()])->call('stopUsing');
    expect($site->pages()->where('template_active', false)->count())->toBe(0)
        ->and($site->forms()->where('template_active', false)->count())->toBe(0);
});

test('template content loads only into collections that never held an entry — owner deletions never come back', function () {
    resetTemplateCollections('hairco', ['Services']);
    [$owner, $site] = pipelineSite();
    $installer = app(TemplateInstaller::class);
    $row = $installer->saveCuratedToSite($site, 'hairco');
    $installer->apply($site, $row);

    $team = $site->collections()->where('slug', 'team')->firstOrFail();
    $loaded = $team->items()->count();
    expect($loaded)->toBeGreaterThan(0)                                         // template content
        ->and($site->collections()->where('slug', 'services')->first()->items()->count())->toBe(0); // reset

    // The owner clears Team and edits Pricing; a re-apply / update changes neither.
    $team->items()->get()->each->delete();
    $pricing = $site->collections()->where('slug', 'pricing')->firstOrFail();
    $first = $pricing->items()->orderBy('position')->first();
    $first->update(['data' => ['title' => 'Owner price']]);
    $pricingCount = $pricing->items()->count();

    $installer->apply($site->fresh(), $row->fresh());
    $installer->refreshAppliedSites('hairco');
    expect($team->items()->count())->toBe(0)
        ->and($pricing->items()->count())->toBe($pricingCount)
        ->and($first->fresh()->data['title'])->toBe('Owner price');
});

test('Admin › Templates lists the template\'s collections and saves which ones reset', function () {
    $tpl = publishBuiltinTemplate('hairco');
    $super = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($super);
    $super->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    Livewire\Livewire::actingAs($super->refresh())->test(PlatformTemplatesPage::class)
        ->call('startEdit', $tpl->id)
        ->assertSee('Collections when a site uses this template')->assertSee('About Points')
        ->set('edit.reset_collections', ['About Points', 'Team', 'Not A Collection'])
        ->call('saveEdit')->assertHasNoErrors();

    expect($tpl->fresh()->reset_collections)->toBe(['About Points', 'Team']);   // unknown names dropped
});
