<?php

use App\Models\ApiToken;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

function productApiSite(): array
{
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id]);

    return [$owner, $site];
}

function productApiKey(User $owner, Site $site, ?array $abilities): string
{
    $raw = Str::random(64);
    ApiToken::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'name' => 'mgmt',
        'token' => hash('sha256', $raw), 'token_preview' => substr($raw, 0, 6),
        'abilities' => $abilities, 'expires_at' => now()->addDay(),
    ]);

    return $raw;
}

test('products can be created, updated and deleted through the token API', function () {
    [$owner, $site] = productApiSite();
    $k = productApiKey($owner, $site, ['store.manage']);

    $this->withToken($k)->postJson("/api/sites/{$site->name}/products/manage", [
        'name' => 'Argan Oil', 'price_cents' => 1900, 'category' => 'Treatment', 'inventory' => 5,
    ])->assertCreated()->assertJsonPath('product.slug', 'argan-oil')
        ->assertJsonPath('product.price_cents', 1900)->assertJsonPath('product.in_stock', true);

    $this->withToken($k)->patchJson("/api/sites/{$site->name}/products/manage/argan-oil", [
        'price_cents' => 2100, 'inventory' => 0,
    ])->assertOk()->assertJsonPath('product.price_cents', 2100)->assertJsonPath('product.in_stock', false);

    expect($this->withToken($k)->getJson("/api/sites/{$site->name}/products/manage")->json('products'))->toHaveCount(1);
    $this->withToken($k)->deleteJson("/api/sites/{$site->name}/products/manage/argan-oil")->assertOk();
    expect($site->products()->count())->toBe(0);
});

test('product writes require the store.manage ability and stay tenant-scoped', function () {
    [$owner, $site] = productApiSite();
    [$other, $otherSite] = productApiSite();
    $payload = ['name' => 'X', 'price_cents' => 100];

    // Token without the ability.
    $weak = productApiKey($owner, $site, ['posts.manage']);
    $this->withToken($weak)->postJson("/api/sites/{$site->name}/products/manage", $payload)->assertForbidden();

    // Right ability, but the token is scoped to a different site.
    $k = productApiKey($owner, $site, ['store.manage']);
    $this->withToken($k)->postJson("/api/sites/{$otherSite->name}/products/manage", $payload)->assertForbidden();

    // No token at all (withToken persists as default headers — clear them).
    $this->flushHeaders();
    $this->postJson("/api/sites/{$site->name}/products/manage", $payload)->assertUnauthorized();
});
