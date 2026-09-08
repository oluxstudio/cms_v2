<?php

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['publishing.subdomain_base' => 'sites.test']));

it('resolves {site}.{base} hosts to the site and refuses reserved or unknown labels', function () {
    $site = Site::factory()->create(['name' => 'janes-salon', 'user_id' => User::factory()->create()->id]);

    expect(Site::forSubdomainHost('janes-salon.sites.test')?->id)->toBe($site->id)
        ->and(Site::forSubdomainHost('JANES-SALON.sites.test')?->id)->toBe($site->id)
        ->and(Site::forSubdomainHost('nobody.sites.test'))->toBeNull()
        ->and(Site::forSubdomainHost('cms.sites.test'))->toBeNull()
        ->and(Site::forSubdomainHost('janes-salon.other.test'))->toBeNull()
        ->and(Site::forSubdomainHost('sites.test'))->toBeNull()
        ->and($site->subdomainHost())->toBe('janes-salon.sites.test')
        ->and($site->publicUrl())->toBe('https://janes-salon.sites.test');

    expect(Site::validSubdomainLabel('www'))->toBeFalse()
        ->and(Site::validSubdomainLabel('a'))->toBeFalse()
        ->and(Site::validSubdomainLabel('-bad'))->toBeFalse()
        ->and(Site::validSubdomainLabel('good-name-1'))->toBeTrue();
});

it('serves the site shell on its subdomain without any go-live step', function () {
    Site::factory()->create(['name' => 'janes-salon', 'user_id' => User::factory()->create()->id]);

    $res = $this->get('http://janes-salon.sites.test/');
    $res->assertOk();
    // Either the built renderer (X-Olux-Live header) or the holding page —
    // both mean the host resolved to the site instead of the admin app.
    expect($res->headers->get('X-Olux-Live') === 'janes-salon' || str_contains($res->getContent(), 'janes-salon'))->toBeTrue();

    // Unknown label falls through to the platform (no site) → not the shell.
    $this->get('http://nobody.sites.test/')->assertHeaderMissing('X-Olux-Live');

    // The bare base domain is the marketing site, never a tenant.
    $this->get('http://sites.test/')->assertHeaderMissing('X-Olux-Live');
});

it('approves subdomain certificates at the Caddy gate', function () {
    Site::factory()->create(['name' => 'janes-salon', 'user_id' => User::factory()->create()->id]);

    $this->get('/caddy/ask?domain=janes-salon.sites.test')->assertOk();
    $this->get('/caddy/ask?domain=nobody.sites.test')->assertStatus(403);
});
