<?php

use App\Livewire\SiteApiKeys;
use App\Livewire\SiteComponent;
use App\Models\AccountActivityLog;
use App\Models\ApiToken;
use App\Models\Site;
use App\Models\User;
use App\Services\AccountActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('login fires an account activity record', function () {
    $user = User::factory()->create();
    $this->actingAs($user); // does not fire Login event

    event(new \Illuminate\Auth\Events\Login('web', $user, false));

    $log = AccountActivityLog::where('account_id', $user->id)->where('action', 'login')->first();
    expect($log)->not->toBeNull()->and($log->category)->toBe('Login');
});

test('creating a site and API key records account activity', function () {
    $owner = User::factory()->create();
    Auth::login($owner);

    Livewire::actingAs($owner)->test(SiteComponent::class)
        ->set('form.name', 'act-'.Str::lower(Str::random(6)))
        ->set('form.domain', 'act.test')
        ->set('form.owner', $owner->name)
        ->call('create');

    $site = Site::where('user_id', $owner->id)->first();
    expect(AccountActivityLog::where('account_id', $owner->id)->where('action', 'site_created')->exists())->toBeTrue();

    Livewire::actingAs($owner)->test(SiteApiKeys::class, ['site' => $site])
        ->set('newName', 'CI key')->call('generate');

    $apiLog = AccountActivityLog::where('account_id', $owner->id)->where('action', 'api_key_created')->first();
    expect($apiLog)->not->toBeNull()->and($apiLog->description)->toContain($site->name);
});

test('a token-authenticated write is recorded, reads are not', function () {
    $owner = User::factory()->create();
    $site = Site::create(['user_id'=>$owner->id,'name'=>'aw-'.uniqid(),'domain'=>'aw.test','owner'=>$owner->name,'description'=>'t']);
    $raw = Str::random(64);
    ApiToken::create(['user_id'=>$owner->id,'site_id'=>$site->id,'name'=>'k','token'=>hash('sha256',$raw),'token_preview'=>substr($raw,0,8)]);
    $auth = ['Authorization' => 'Bearer '.$raw];

    $this->getJson("/api/sites/{$site->name}/components", $auth);       // read — not logged
    $this->postJson("/api/sites/{$site->name}/posts", ['title'=>'Hi'], $auth)->assertCreated(); // write — logged

    $calls = AccountActivityLog::where('account_id', $owner->id)->where('action', 'api_call')->get();
    expect($calls)->toHaveCount(1)
        ->and($calls->first()->title)->toContain('POST');
});

test('the audit trail groups by day', function () {
    $user = User::factory()->create();
    AccountActivity::record($user->id, 'login', 'Logged in', ['category' => 'Login']);
    AccountActivity::record($user->id, 'password_changed', 'Changed password', ['category' => 'Security']);

    $groups = AccountActivityLog::where('account_id', $user->id)->get()
        ->groupBy(fn ($l) => $l->created_at->isToday() ? 'Today' : $l->created_at->format('F j, Y'));

    expect($groups)->toHaveKey('Today')
        ->and($groups['Today'])->toHaveCount(2);
});
