<?php

use App\Livewire\SiteTemplatesPage;
use App\Models\Site;
use App\Models\User;
use App\Services\TemplateInstaller;
use Livewire\Livewire;

function designsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'des-'.uniqid(), 'domain' => 'des-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site];
}

test('the My Designs page is routed, permission-gated, and the old templates URL lands on it', function () {
    [$owner, $site] = designsSite();
    $this->actingAs($owner)->get("/{$site->name}/designs")->assertOk()->assertSee('saved designs');
    $this->actingAs($owner)->get("/{$site->name}/templates")->assertRedirect("/{$site->name}/designs");
    $this->actingAs(User::factory()->create())->get("/{$site->name}/designs")->assertForbidden();
});

test('switching the active design binds the renderer, is exclusive, scaffolds once and keeps content', function () {
    [$owner, $site] = designsSite();
    $installer = app(TemplateInstaller::class);
    $verita = $installer->saveCuratedToSite($site, 'verita');
    $hairco = $installer->saveCuratedToSite($site, 'hairco');

    // A pre-existing page with content must survive every switch.
    $page = $site->pages()->create(['name' => 'Custom', 'url' => 'my-custom-'.uniqid(), 'keywords' => '', 'is_published' => true]);

    $installer->apply($site, $verita);
    $site->refresh();
    expect($site->template)->toBe('verita')
        ->and($verita->fresh()->isApplied())->toBeTrue()
        ->and($site->pages()->count())->toBeGreaterThan(1);
    $countAfterFirst = $site->pages()->count();

    // Re-apply is idempotent.
    $installer->apply($site, $verita->fresh());
    expect($site->pages()->count())->toBe($countAfterFirst);

    // Switch: exclusivity + content preserved.
    $installer->apply($site, $hairco);
    $site->refresh();
    expect($site->template)->toBe('hairco')
        ->and($hairco->fresh()->isApplied())->toBeTrue()
        ->and($verita->fresh()->isApplied())->toBeFalse()
        ->and($site->pages()->where('id', $page->id)->exists())->toBeTrue();

    // Preview follows the active renderer (shell is built for hairco).
    expect($site->previewUrl())->toContain('nuxt-preview/hairco');
});

test('the active design cannot be removed; inactive ones can; features survive a switch', function () {
    [$owner, $site] = designsSite();
    $installer = app(TemplateInstaller::class);
    $verita = $installer->saveCuratedToSite($site, 'verita');
    $tek = $installer->saveCuratedToSite($site, 'tekstack');
    $installer->apply($site, $verita);
    $site->enableFeature('bookings');

    $c = Livewire::actingAs($owner)->test(SiteTemplatesPage::class, ['site' => $site->fresh()]);
    $c->call('removeTemplate', (string) $verita->id);
    expect($site->installedTemplates()->whereKey($verita->id)->exists())->toBeTrue(); // blocked

    $c->call('removeTemplate', (string) $tek->id);
    expect($site->installedTemplates()->whereKey($tek->id)->exists())->toBeFalse();

    // Modules are template-independent.
    $c->call('useDesign', 'hairco');
    $this->actingAs($owner)->get("/{$site->name}/bookings")->assertOk();

    // stopUsing → back to blank, preview falls back (never null).
    $c->call('stopUsing');
    expect($site->fresh()->template)->toBe('blank')
        ->and($site->fresh()->previewUrl())->toContain('nuxt-preview');
});
