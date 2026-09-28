<?php

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => config(['domains.edge_token' => 'edge-secret', 'publishing.subdomain_base' => 'oluxstudio.com']));

test('edge feed is hidden without the token', function () {
    $this->get('/internal/edge/routes')->assertNotFound();
    $this->get('/internal/edge/routes', ['X-Edge-Token' => 'wrong'])->assertNotFound();
    config(['domains.edge_token' => '']);
    $this->get('/internal/edge/routes', ['X-Edge-Token' => ''])->assertNotFound();
});

test('edge feed lists one TLS router per verified custom domain only', function () {
    $owner = User::factory()->create();
    $verified = 'edge'.uniqid().'.org';
    $unverified = 'edgeu'.uniqid().'.org';
    Cache::put('edge-resolves:www.'.$verified, true, 600);
    Site::factory()->create(['user_id' => $owner->id, 'domain' => $verified, 'domain_verified_at' => now()]);
    Site::factory()->create(['user_id' => $owner->id, 'domain' => $unverified, 'domain_verified_at' => null]);
    Site::factory()->create(['user_id' => $owner->id, 'domain' => 'x'.uniqid().'.oluxstudio.com', 'domain_verified_at' => now()]);

    $routers = collect($this->get('/internal/edge/routes', ['X-Edge-Token' => 'edge-secret'])
        ->assertOk()->json('http.routers'));

    $rules = $routers->pluck('rule');
    expect($rules)->toContain('Host(`'.$verified.'`)')
        ->toContain('Host(`www.'.$verified.'`)')
        ->not->toContain('Host(`'.$unverified.'`)');
    expect($rules->filter(fn ($r) => str_contains($r, 'oluxstudio.com')))->toBeEmpty();
    expect($routers->first()['tls']['certResolver'])->toBe('le')
        ->and($routers->first()['service'])->toBe('cms@docker');
});
