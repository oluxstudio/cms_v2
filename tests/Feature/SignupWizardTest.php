<?php

use App\Livewire\SignupWizard;
use App\Mail\FinishSetup;
use App\Mail\VerificationCode;
use App\Models\Service;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sends guests to the register panel, remembers the business type, and resumes the wizard once signed in', function () {
    $this->get('/register?type=barber')->assertRedirect(route('start', ['type' => 'barber']));
    $this->get('/start?type=barber')->assertRedirect(route('login', ['mode' => 'register']))->assertSessionHas('signup_type', 'barber');

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->withSession(['signup_type' => 'barber'])->get('/start')->assertOk()->assertSee('Your business');
    session(['signup_type' => 'barber']);
    Livewire::actingAs($user)->test(SignupWizard::class)->assertSet('step', 2)->assertSet('type', 'barber');
});

it('walks account → business → verify → done, provisioning the salon blueprint', function () {
    Mail::fake();

    $w = Livewire::test(SignupWizard::class)
        ->set('name', 'Jane Smith')->set('email', 'jane@example.test')->set('password', 'secret-pass-1')
        ->set('password_confirmation', 'different')
        ->call('createAccount')->assertHasErrors('password')->assertSet('step', 1);
    $w->set('password', 'secret-pass-1')->set('password_confirmation', 'secret-pass-1')
        ->call('createAccount')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    $user = User::where('email', 'jane@example.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull();
    $this->assertAuthenticatedAs($user);

    // Business name auto-suggests the subdomain.
    $w->set('type', 'barber')->set('business', "Jane's Barbers")->set('purpose', 'Take bookings for my shop')
        ->assertSet('subdomain', 'janes-barbers')
        ->assertSet('available', true)
        ->call('createSite')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    $site = Site::where('name', 'janes-barbers')->first();
    expect($site)->not->toBeNull()
        ->and($site->user_id)->toBe($user->id)
        ->and($site->template)->toBe('hairco')
        ->and($site->hasFeature('bookings'))->toBeTrue()
        ->and($site->getAttr('business_name'))->toBe("Jane's Barbers")
        ->and($site->getAttr('site_purpose'))->toBe('Take bookings for my shop')
        ->and(Service::where('site_id', $site->id)->count())->toBe(5)
        ->and($site->forms()->where('name', 'appointment')->exists())->toBeTrue();
    // Template copy uses the business name, not the slug.
    expect($site->pages()->count())->toBeGreaterThan(1);

    // The setup checklist lands in the site's Todos panel.
    $todo = $site->todos()->first();
    expect($todo)->not->toBeNull()
        ->and($todo->assigned_user_id)->toBe($user->id)
        ->and($todo->items->pluck('label')->all())->toBe(SignupWizard::SETUP_ITEMS)
        ->and($todo->items->pluck('label')->implode(' '))->toContain('domain name')->toContain('template')->toContain('colour theme');

    // Building the site sends the code; capture it from the queued mailable.
    $code = null;
    Mail::assertQueued(VerificationCode::class, function (VerificationCode $m) use (&$code) {
        $code = $m->code;

        return $m->hasTo('jane@example.test');
    });

    $w->set('code', '000000')->call('verify')->assertHasErrors('code')->assertSet('step', 3);
    $w->set('code', $code)->call('verify')->assertHasNoErrors()->assertSet('step', 4);

    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and($user->fresh()->onboarding['wizard']['done'])->toBeTrue();

    // Finished users are sent straight to their dashboard on revisit.
    Livewire::actingAs($user->fresh())->test(SignupWizard::class)->assertRedirect(url("/{$site->name}/dashboard"));
});

it('rejects reserved, invalid and taken subdomains', function () {
    $user = User::factory()->create();
    Site::factory()->create(['name' => 'taken-name']);

    $w = Livewire::actingAs($user)->test(SignupWizard::class)->set('step', 2)->set('type', 'salon')->set('business', 'X Y');
    foreach (['cms', 'www', 'taken-name', 'ab', '!!'] as $label) {
        $w->set('subdomain', $label);
        expect($w->get('available'))->toBeFalse("{$label} should be unavailable");
    }
    // Typed names are auto-slugged into a valid label.
    $w->set('subdomain', 'Bad Name!')->assertSet('subdomain', 'bad-name')->assertSet('available', true);
    $w->set('subdomain', 'cms')->call('createSite')->assertHasErrors('subdomain');
    expect(Site::where('name', 'cms')->exists())->toBeFalse();
});

it('provisions the trades blueprint for tradespeople and skips verify for confirmed emails', function () {
    Mail::fake();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(SignupWizard::class)
        ->set('step', 2)->set('type', 'electrician')->set('business', 'Spark Electrical')
        ->call('createSite')->assertHasNoErrors()->assertSet('step', 4);
    Mail::assertNothingQueued();

    $site = Site::where('name', 'spark-electrical')->first();
    expect($site)->not->toBeNull()
        ->and($site->template)->toBe('verita')
        ->and($site->hasFeature('estimator'))->toBeTrue()
        ->and($site->hasFeature('invoices'))->toBeTrue()
        ->and($site->hasFeature('bookings'))->toBeTrue()
        ->and($site->forms()->where('name', 'quote')->exists())->toBeTrue()
        ->and(Service::where('site_id', $site->id)->count())->toBe(4);
});

it('social signup returns to the wizard and skips verification', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'social_type' => 'google', 'social_id' => 'g-1']);

    // Redirect with intent=signup remembers where to go; the callback honours it.
    $this->withSession(['social.after' => 'start'])->actingAs($user);
    expect(session('social.after'))->toBe('start');

    Livewire::actingAs($user)->test(SignupWizard::class)->assertSet('step', 2)
        ->set('type', 'salon')->set('business', 'Glow Studio')->call('createSite')
        ->assertHasNoErrors()->assertSet('step', 4);
});

it('resumes a mid-wizard user from the site picker and nudges abandoners once', function () {
    Mail::fake();
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->setOnboarding(['wizard' => ['step' => 2, 'done' => false, 'updated_at' => now()->subDays(2)->toIso8601String()]]);

    $this->actingAs($user)->get('/select-site')->assertRedirect(route('start'));

    $this->artisan('signup:nudge')->assertSuccessful();
    Mail::assertQueued(FinishSetup::class, fn ($m) => $m->hasTo($user->email));

    // Second run: already nudged → nothing more.
    $this->artisan('signup:nudge')->assertSuccessful();
    Mail::assertQueuedCount(1);
});
