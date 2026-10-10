<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialUser;

uses(DatabaseTransactions::class);

function socialAuthFake(string $provider, ?string $email, string $id = 'sid-1', ?string $name = 'Sam Social'): void
{
    $u = (new SocialUser)->map(['id' => $id, 'name' => $name, 'email' => $email, 'avatar' => null]);
    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('redirectUrl', 'scopes', 'fields')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($u);
    Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
}

beforeEach(function () {
    config([
        'services.google.client_id' => 'g', 'services.google.client_secret' => 'g',
        'services.facebook.client_id' => 'f', 'services.facebook.client_secret' => 'f',
    ]);
});

it('offers only Google and Facebook on the login page, for sign in and sign up', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->toContain('auth/google/redirect')
        ->toContain('auth/facebook/redirect')
        ->toContain('auth/google/redirect?intent=signup')
        ->toContain('auth/facebook/redirect?intent=signup')
        ->not->toContain('auth/twitter/redirect')
        ->not->toContain('auth/instagram/redirect')
        ->not->toContain('auth/tiktok/redirect');
});

it('rejects any other provider', function (string $provider) {
    $this->get("/auth/{$provider}/redirect")->assertNotFound();
    $this->get("/auth/{$provider}/callback")->assertNotFound();
})->with(['twitter', 'instagram', 'tiktok', 'github']);

it('explains when a provider has no credentials yet', function () {
    config(['services.facebook.client_id' => null]);

    $this->get('/auth/facebook/redirect')->assertRedirect(route('login'))->assertSessionHasErrors('email');
});

it('creates a new account from Facebook and continues into the signup wizard', function () {
    $email = 'fb-'.uniqid().'@example.com';
    socialAuthFake('facebook', $email, 'fb-'.uniqid());

    $this->get('/auth/facebook/callback')->assertRedirect(route('start'));

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull()
        ->and($user->social_type)->toBe('facebook')
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('signs an existing Google user straight in', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'social_type' => 'google', 'social_id' => 'g-'.uniqid()]);
    socialAuthFake('google', $user->email, $user->social_id);

    $this->get('/auth/google/callback')->assertRedirect($user->landingUrl());
    $this->assertAuthenticatedAs($user);
});

it('refuses a social account that shares no email', function () {
    socialAuthFake('facebook', null, 'fb-'.uniqid());

    $this->get('/auth/facebook/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('serves the public privacy policy with a linkable data-deletion section', function () {
    $html = $this->get('/privacy')->assertOk()->getContent();

    expect($html)->toContain('<title>Privacy Policy – Olux CMS</title>')
        ->toContain('id="data-deletion"')
        ->toContain('href="#data-deletion"')
        ->toContain('Data deletion request')
        ->toContain('ico.org.uk');

    expect($this->get(route('login'))->getContent())->toContain(route('privacy'));
    expect($this->get(route('landing'))->getContent())->toContain(route('privacy').'#data-deletion');
});

it('links the privacy policy from the footer of every public page', function (string $path) {
    expect($this->get($path)->assertOk()->getContent())->toContain(route('privacy'));
})->with(['/', '/login', '/designs', '/tutorial', '/testimonials/new', '/salons']);

it('sends people to Facebook with our own callback and only the email + public profile permissions', function () {
    config(['services.facebook.redirect' => 'http://localhost:8000/auth/facebook/callback']); // stale .env value is ignored

    $location = $this->get('/auth/facebook/redirect?intent=signup')->assertRedirect()->headers->get('Location');
    parse_str(parse_url($location, PHP_URL_QUERY), $q);

    expect($location)->toStartWith('https://www.facebook.com/')
        ->and($q['redirect_uri'])->toBe(route('social.callback', 'facebook'))
        ->and(explode(',', $q['scope']))->toEqualCanonicalizing(['email', 'public_profile']);
    expect(session('social.after'))->toBe('start');
});

it('explains when someone cancels on the Facebook screen', function () {
    $this->get('/auth/facebook/callback?error=access_denied&error_code=200&error_reason=user_denied')
        ->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('stores the social profile picture as a file, not its long expiring URL', function () {
    Storage::fake('public');
    Http::fake(['https://scontent.example/*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg'])]);
    $longUrl = 'https://scontent.example/pic.jpg?'.str_repeat('x', 600); // real Facebook URLs exceed 255 chars

    // New account: photo saved to avatars/ on the public disk
    $email = 'fbpic-'.uniqid().'@example.com';
    $u = (new SocialUser)->map(['id' => 'fb-'.uniqid(), 'name' => 'Pic Person', 'email' => $email, 'avatar' => $longUrl]);
    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('redirectUrl', 'scopes', 'fields')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($u);
    Socialite::shouldReceive('driver')->with('facebook')->andReturn($driver);

    $this->get('/auth/facebook/callback')->assertRedirect(route('start'));
    $user = User::where('email', $email)->first();
    expect($user->avatar)->toStartWith('avatars/')->toEndWith('.jpg');
    Storage::disk('public')->assertExists($user->avatar);
});

it('keeps an existing photo when a social account is linked', function () {
    Storage::fake('public');
    Http::fake(['https://scontent.example/*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg'])]);
    $longUrl = 'https://scontent.example/pic.jpg?'.str_repeat('x', 600);
    $existing = User::factory()->create(['email' => 'fbkeep-'.uniqid().'@example.com', 'avatar' => 'avatars/mine.png']);
    $u2 = (new SocialUser)->map(['id' => 'fb-'.uniqid(), 'name' => 'Keep', 'email' => $existing->email, 'avatar' => $longUrl]);
    $driver2 = Mockery::mock(AbstractProvider::class);
    $driver2->shouldReceive('redirectUrl', 'scopes', 'fields')->andReturnSelf();
    $driver2->shouldReceive('user')->andReturn($u2);
    Socialite::shouldReceive('driver')->with('facebook')->andReturn($driver2);

    $this->get('/auth/facebook/callback');
    expect($existing->fresh())->avatar->toBe('avatars/mine.png')->social_type->toBe('facebook');
});
