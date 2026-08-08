<?php
use App\Livewire\SiteApiKeys;
use App\Models\AccountMember;
use App\Models\ApiToken;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function apiKeySite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id'=>$owner->id,'name'=>'ak-'.uniqid(),'domain'=>'ak.test','owner'=>$owner->name,'description'=>'t']);

    return [$owner, $site];
}

test('an admin generates a key pinned to this site, shown once, then revokes it', function () {
    [$owner, $site] = apiKeySite();

    $c = Livewire::actingAs($owner)->test(SiteApiKeys::class, ['site' => $site])
        ->set('newName', 'Website build')
        ->set('newAbilities', ['pages.manage'])
        ->set('newExpiry', '90')
        ->call('generate')
        ->assertSet('generatedToken', fn ($t) => is_string($t) && strlen($t) === 64);

    $token = ApiToken::where('site_id', $site->id)->first();
    expect($token)->not->toBeNull()
        ->and($token->site_id)->toBe($site->id)          // pinned to THIS site
        ->and($token->abilities)->toBe(['pages.manage'])
        ->and($token->expires_at)->not->toBeNull()
        ->and($token->token)->not->toBe($c->get('generatedToken')); // stored hashed

    $c->call('revoke', $token->id);
    expect(ApiToken::where('site_id', $site->id)->count())->toBe(0);
});

test('a non-admin member cannot open the site API keys panel', function () {
    [$owner, $site] = apiKeySite();
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id]);

    Livewire::actingAs($member)->test(SiteApiKeys::class, ['site' => $site])->assertStatus(403);
});

test('a key created in the site panel actually authenticates against that site only', function () {
    [$owner, $site] = apiKeySite();
    $other = Site::create(['user_id'=>$owner->id,'name'=>'ak2-'.uniqid(),'domain'=>'ak2.test','owner'=>$owner->name,'description'=>'t']);

    // Capture the raw token by generating, then re-hash check via the API.
    $c = Livewire::actingAs($owner)->test(SiteApiKeys::class, ['site' => $site])
        ->set('newName', 'Scoped')->call('generate');
    $raw = $c->get('generatedToken');
    $auth = ['Authorization' => 'Bearer '.$raw];

    $this->postJson("/api/sites/{$site->name}/posts", ['title' => 'Yes'], $auth)->assertCreated();
    $this->postJson("/api/sites/{$other->name}/posts", ['title' => 'No'], $auth)->assertStatus(403);
});
