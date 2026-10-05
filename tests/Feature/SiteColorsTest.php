<?php

use App\Livewire\SitePropertiesPage;
use App\Models\Site;
use App\Models\User;
use App\Support\SiteColors;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

// The Properties page's Colours tab: one picker per template --color-* variable.
uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->pkgKey = 'zz-colors-'.uniqid();
    $this->pkgDir = resource_path('templates/'.$this->pkgKey);
    File::ensureDirectoryExists($this->pkgDir.'/tokens');
    File::put($this->pkgDir.'/template.json', json_encode(['key' => $this->pkgKey, 'name' => 'Colours test']));
    File::put($this->pkgDir.'/tokens/css-colors.json', json_encode(['color-primary' => '#ec0470', 'color-frame' => '#2ba98f']));
    File::put($this->pkgDir.'/tokens/colors.json', json_encode(['accent' => '#ec0470', 'navy' => '#14181d']));
});

afterEach(fn () => File::deleteDirectory($this->pkgDir));

function colorsSite(string $template, array $theme = []): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'colors-'.uniqid(), 'domain' => 'colors-'.uniqid().'.test',
        'owner' => 'x', 'description' => 't', 'template' => $template, 'theme' => $theme]);

    return [$owner, $site];
}

test('the template colours list with their defaults, overrides applied', function () {
    [, $site] = colorsSite($this->pkgKey, ['accent' => '#111111', 'color-frame' => '#000000']);

    expect(SiteColors::defaults($site))->toBe(['color-primary' => '#ec0470', 'color-frame' => '#2ba98f'])
        ->and(SiteColors::current($site))->toBe(['color-primary' => '#ec0470', 'color-frame' => '#000000'])
        ->and(SiteColors::label('color-primary'))->toBe('Primary');
});

test('saving stores only real changes and leaves other theme keys alone', function () {
    [, $site] = colorsSite($this->pkgKey, ['accent' => '#111111', 'color-frame' => '#000000']);

    // primary changes; frame goes back to its default (so its override is dropped)
    SiteColors::save($site, ['color-primary' => '#3366ff', 'color-frame' => '#2BA98F']);

    expect($site->fresh()->theme)->toBe(['accent' => '#111111', 'color-primary' => '#3366ff']);
});

test('the properties page edits the colours and rejects anything that is not a colour', function () {
    [$owner, $site] = colorsSite($this->pkgKey);

    $page = Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->assertSet('colors', ['color-primary' => '#ec0470', 'color-frame' => '#2ba98f'])
        ->assertSee('--color-primary');

    $page->set('colors.color-primary', 'red; } body { display:none')->call('save')
        ->assertHasErrors('colors.color-primary');
    expect($site->fresh()->theme ?? [])->not->toHaveKey('color-primary');

    $page->set('colors.color-primary', 'rgb(51, 102, 255)')->call('save')->assertHasNoErrors();
    expect($site->fresh()->theme)->toMatchArray(['color-primary' => 'rgb(51, 102, 255)']);
});

test('a site whose template has no colour variables shows an empty Colours tab', function () {
    [$owner, $site] = colorsSite('no-such-template');

    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->assertSet('colors', [])
        ->assertSee('doesn');
});

test('a template sync keeps the colours the owner picked', function () {
    [, $site] = colorsSite($this->pkgKey, ['accent' => '#000000', 'color-primary' => '#04beec']);
    $row = \App\Models\SiteTemplate::create(['site_id' => $site->id, 'source' => 'builtin', 'builtin_key' => $this->pkgKey, 'name' => 'Colours test']);

    $apply = new ReflectionMethod(\App\Services\TemplateInstaller::class, 'applyTheme');
    $apply->invoke(app(\App\Services\TemplateInstaller::class), $site, $row, $this->pkgKey, true);

    // the template's role colours are re-applied; the picked --color-primary survives
    expect($site->fresh()->theme)->toMatchArray(['accent' => '#ec0470', 'navy' => '#14181d', 'color-primary' => '#04beec'])
        ->and(SiteColors::current($site->fresh())['color-primary'])->toBe('#04beec');
});

test('an uploaded (or GitHub) template lists its colours from its own package', function () {
    $key = \App\Support\TemplatePaths::UPLOAD_PREFIX.'colors'.strtolower(\Illuminate\Support\Str::random(6));
    $dir = \App\Support\TemplatePaths::packageDir($key);
    File::ensureDirectoryExists($dir.'/tokens');
    File::put($dir.'/template.json', json_encode(['key' => $key, 'name' => 'Uploaded']));
    File::put($dir.'/tokens/css-colors.json', json_encode(['color-primary' => '#ec0470']));
    try {
        [, $site] = colorsSite($key, ['color-primary' => '#04beec']);
        expect(\App\Templates\TemplateRegistry::find($key))->toBeNull()   // not a built-in package
            ->and(SiteColors::defaults($site))->toBe(['color-primary' => '#ec0470'])
            ->and(SiteColors::current($site))->toBe(['color-primary' => '#04beec']);
    } finally {
        File::deleteDirectory(dirname($dir));
    }
});
