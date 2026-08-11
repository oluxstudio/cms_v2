<?php

use App\Models\ApiToken;
use App\Models\Component;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

function ctxSite(User $owner, string $suffix = ''): Site
{
    return Site::create(['user_id' => $owner->id, 'name' => 'ctx-'.$suffix.uniqid(), 'domain' => 'ctx.test', 'owner' => $owner->name, 'description' => 't']);
}

/** @param  array<string>|null  $abilities */
function mintKey(User $owner, ?Site $site, ?array $abilities): string
{
    $raw = Str::random(64);
    ApiToken::create([
        'user_id' => $owner->id, 'site_id' => $site?->id, 'name' => 'k',
        'token' => hash('sha256', $raw), 'token_preview' => substr($raw, 0, 6),
        'abilities' => $abilities, 'expires_at' => now()->addDay(),
    ]);

    return $raw;
}

test('a site-scoped key creates a component with no site in the URL', function () {
    $owner = User::factory()->create();
    $site = ctxSite($owner);
    $k = mintKey($owner, $site, ['components.manage']);

    $res = $this->withToken($k)->postJson('/api/site/components', [
        'name' => 'Hero', 'nodes' => [['label' => 'title', 'type' => 'text', 'value' => 'Hi']],
    ])->assertCreated();

    $id = $res->json('component.id');
    $cmp = Component::find($id);
    expect($cmp->site_id)->toBe($site->id)->and($cmp->name)->toBe('Hero');

    // Reads resolve the same site.
    $this->withToken($k)->getJson('/api/site/components')
        ->assertOk()->assertJsonFragment(['id' => $id]);
    $this->withToken($k)->getJson('/api/site')
        ->assertOk()->assertJsonFragment(['site' => $site->name]);
});

test('a key without the ability is forbidden', function () {
    $owner = User::factory()->create();
    $site = ctxSite($owner);
    $k = mintKey($owner, $site, ['posts.manage']); // not components.manage

    $this->withToken($k)->postJson('/api/site/components', [
        'name' => 'Hero', 'nodes' => [['label' => 't', 'type' => 'text', 'value' => 'x']],
    ])->assertForbidden();
});

test('an invalid key is unauthorized', function () {
    $this->withToken('not-a-real-key')->getJson('/api/site')->assertUnauthorized();
});

test('an unscoped key with several sites is ambiguous without a header', function () {
    $owner = User::factory()->create();
    ctxSite($owner, 'a');
    ctxSite($owner, 'b');
    $k = mintKey($owner, null, ['components.manage']); // unscoped

    $this->withToken($k)->getJson('/api/site')->assertStatus(409);
});

test('an unscoped key can target a site via the X-Olux-Site header', function () {
    $owner = User::factory()->create();
    $a = ctxSite($owner, 'a');
    ctxSite($owner, 'b');
    $k = mintKey($owner, null, ['components.manage']);

    // Valid site → works and lands in that site.
    $res = $this->withToken($k)->withHeaders(['X-Olux-Site' => $a->name])
        ->postJson('/api/site/components', ['name' => 'H', 'nodes' => [['label' => 't', 'type' => 'text', 'value' => 'x']]])
        ->assertCreated();
    expect(Component::find($res->json('component.id'))->site_id)->toBe($a->id);

    // A site the key/user can't access → 403.
    $stranger = Site::create(['user_id' => User::factory()->create()->id, 'name' => 'other-'.uniqid(), 'domain' => 'o.test', 'owner' => 'x', 'description' => 't']);
    $this->withToken($k)->withHeaders(['X-Olux-Site' => $stranger->name])
        ->getJson('/api/site')->assertForbidden();
});
