<?php

use App\Livewire\PlatformTemplatesPage;
use App\Models\Template;
use App\Models\User;
use App\Services\BuiltinTemplateUpdates;
use App\Services\TemplateCatalogWriter;
use App\Services\TwoFactor;
use App\Templates\TemplateRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(fn () => Storage::fake(config('templates.disk')));

/** The built-in "verita" package, in the catalog as it is today (no recorded hash). */
function builtinVerita(): Template
{
    $t = app(TemplateCatalogWriter::class)->upsert(TemplateRegistry::find('verita'));
    $v = $t->latestVersion;
    $v->update(['manifest' => collect($v->manifest)->except('source_hash')->all()]);

    return $t->fresh();
}

function builtinSuper(): User
{
    $u = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($u);
    $u->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $u->refresh();
}

test('the first check records a baseline; a changed repo copy is flagged as an update', function () {
    $t = builtinVerita();
    $updates = app(BuiltinTemplateUpdates::class);

    expect($updates->check()['verita'])->toBe('baseline')
        ->and($t->fresh()->latestVersion->manifest['source_hash'])->toBe($updates->fingerprint('verita'))
        ->and($updates->check()['verita'])->toBe('current')
        ->and($t->fresh()->pending_update)->toBeNull();

    // A deploy changed the template (simulated: the live version's hash no longer matches).
    $v = $t->fresh()->latestVersion;
    $v->update(['manifest' => array_merge($v->manifest, ['source_hash' => 'old'])]);
    expect($updates->check()['verita'])->toBe('update')
        ->and($t->fresh()->pending_update['hash'])->toBe($updates->fingerprint('verita'));
});

test('Update template publishes the repo copy as a NEW version (so Update sites can move sites) and keeps admin edits', function () {
    $t = builtinVerita();
    $t->update(['description' => 'Admin-written tagline', 'status' => 'draft']);
    $updates = app(BuiltinTemplateUpdates::class);
    $updates->check();
    $v = $t->fresh()->latestVersion;
    $v->update(['manifest' => array_merge($v->manifest, ['source_hash' => 'old'])]);
    $updates->check();
    $before = $t->fresh()->latest_version_id;

    Livewire::actingAs(builtinSuper())->test(PlatformTemplatesPage::class)
        ->call('applyBuiltinUpdate', $t->id)
        ->assertDispatched('toast');

    $t->refresh();
    expect($t->latest_version_id)->not->toBe($before)
        ->and($t->latestVersion->version)->toStartWith(TemplateRegistry::find('verita')->version().'+')
        ->and($t->latestVersion->manifest['source_hash'])->toBe($updates->fingerprint('verita'))
        ->and($t->pending_update)->toBeNull()
        ->and($t->description)->toBe('Admin-written tagline')   // catalog row untouched
        ->and($t->status)->toBe('draft');

    // Pressing it again with nothing new keeps the same version.
    Livewire::actingAs(builtinSuper())->test(PlatformTemplatesPage::class)->call('applyBuiltinUpdate', $t->id);
    expect($t->fresh()->latest_version_id)->toBe($t->latest_version_id);
});

test('only super admins can apply, and only to built-in templates', function () {
    $t = builtinVerita();
    // Non-super users can't open the admin page at all.
    Livewire::actingAs(User::factory()->create())->test(PlatformTemplatesPage::class)->assertForbidden();
    expect($t->fresh()->latestVersion->manifest)->not->toHaveKey('source_hash');

    $upload = Template::create(['uuid' => (string) Str::uuid(), 'name' => 'Up', 'slug' => 'up-'.uniqid(), 'source' => 'upload', 'builtin_key' => 'u-'.uniqid(), 'status' => 'draft']);
    Livewire::actingAs(builtinSuper())->test(PlatformTemplatesPage::class)
        ->call('applyBuiltinUpdate', $upload->id)->assertStatus(422);
});

test('templates:check-updates reports per template', function () {
    builtinVerita();
    $this->artisan('templates:check-updates')->expectsOutputToContain('verita')->assertSuccessful();
});
