<?php

use App\Models\ApiToken;
use App\Models\Contact;
use App\Models\Module;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

function securitySite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'sec-'.uniqid(),
        'domain' => 'client-site.test', 'owner' => $owner->name, 'description' => 'test',
    ]);

    return [$owner, $site];
}

function tokenFor(User $user, array $attrs = []): array
{
    $raw = Str::random(64);
    $token = ApiToken::create(array_merge([
        'user_id' => $user->id, 'name' => 't-'.uniqid(),
        'token' => hash('sha256', $raw), 'token_preview' => substr($raw, 0, 8),
    ], $attrs));

    return [$token, ['Authorization' => 'Bearer '.$raw]];
}

test('tokens are stored hashed and /api/me introspects scope', function () {
    [$owner, $site] = securitySite();
    [$token, $auth] = tokenFor($owner, ['site_id' => $site->id, 'abilities' => ['posts.manage'], 'expires_at' => now()->addDays(30)]);

    // The DB row never contains the raw bearer.
    expect($token->token)->not->toContain(substr($auth['Authorization'], -20));

    $me = $this->getJson('/api/me', $auth)->assertOk()->json();
    expect($me['site'])->toBe($site->name)
        ->and($me['abilities'])->toBe(['posts.manage'])
        ->and($me['expires_at'])->not->toBeNull()
        ->and($me['sites'])->toBe([$site->name]);
});

test('expired tokens are rejected with 401', function () {
    [$owner, $site] = securitySite();
    [, $auth] = tokenFor($owner, ['expires_at' => now()->subMinute()]);

    $this->getJson('/api/me', $auth)->assertStatus(401);
    $this->postJson("/api/sites/{$site->name}/posts", ['title' => 'No'], $auth)->assertStatus(401);
});

test('a site-scoped token cannot write to another site', function () {
    [$owner, $site] = securitySite();
    $other = Site::create([
        'user_id' => $owner->id, 'name' => 'sec2-'.uniqid(),
        'domain' => 'other.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    [, $auth] = tokenFor($owner, ['site_id' => $other->id]);

    // Owner has full permissions on BOTH sites — the token scope is what blocks.
    $this->postJson("/api/sites/{$site->name}/posts", ['title' => 'Blocked'], $auth)->assertStatus(403);
    $this->postJson("/api/sites/{$other->name}/posts", ['title' => 'Allowed'], $auth)->assertCreated();
});

test('an ability-limited token is blocked outside its abilities', function () {
    [$owner, $site] = securitySite();
    [, $auth] = tokenFor($owner, ['abilities' => ['posts.manage']]);

    $this->postJson("/api/sites/{$site->name}/posts", ['title' => 'Fine'], $auth)->assertCreated();
    $this->postJson("/api/sites/{$site->name}/pages", ['name' => 'No', 'url' => '/no'], $auth)->assertStatus(403);
    $this->postJson("/api/sites/{$site->name}/components", ['name' => 'No'], $auth)->assertStatus(403);
});

test('the honeypot silently drops bot submissions', function () {
    [$owner, $site] = securitySite();
    $before = Contact::count();

    $this->postJson("/api/sites/{$site->name}/contact", [
        'name' => 'Bot', 'email' => 'bot@spam.test', 'message' => 'Buy now!', '_hp' => 'gotcha',
    ])->assertStatus(201)->assertJsonPath('ok', true);

    expect(Contact::count())->toBe($before); // nothing persisted
});

test('browser submissions from foreign origins are rejected, own domain and no-origin pass', function () {
    [$owner, $site] = securitySite();
    $payload = ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hello!'];

    // Foreign website posting into this site from a browser → blocked.
    $this->postJson("/api/sites/{$site->name}/contact", $payload, ['Origin' => 'https://evil.example.com'])
        ->assertStatus(403);

    // The site's own custom domain (± www) → allowed.
    $this->postJson("/api/sites/{$site->name}/contact", $payload, ['Origin' => 'https://www.client-site.test'])
        ->assertStatus(201);

    // Server-to-server (no Origin header) → allowed.
    $this->postJson("/api/sites/{$site->name}/contact", $payload)->assertStatus(201);
});

test('public module submissions land pending by default and published with auto_publish', function () {
    [$owner, $site] = securitySite();
    $collection = $site->collections()->create([
        'name' => 'Testimonials', 'slug' => 'Testimonials', 'type' => 'list',
        'fields' => [['key' => 'quote', 'label' => 'Quote', 'type' => 'text']],
        'is_public' => true, 'allow_submit' => true, 'auto_publish' => false,
    ]);
    Module::create([
        'site_id' => $site->id, 'key' => 'testimonials', 'name' => 'Testimonials',
        'collection_id' => $collection->id, 'capabilities' => ['submit' => true], 'enabled' => true,
    ]);

    $this->postJson("/api/sites/{$site->name}/modules/testimonials/items", ['quote' => 'Great!'])
        ->assertCreated();
    expect($collection->items()->first()->status)->toBe('pending');

    $collection->update(['auto_publish' => true]);
    $this->postJson("/api/sites/{$site->name}/modules/testimonials/items", ['quote' => 'Instant!'])
        ->assertCreated();
    expect($collection->items()->latest('id')->first()->status)->toBe('published');
});
