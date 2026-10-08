<?php

use App\Livewire\LandingTestimonials;
use App\Livewire\PlatformTestimonialsPage;
use App\Livewire\TestimonialSubmitPage;
use App\Models\PlatformSetting;
use App\Models\PlatformTestimonial;
use App\Models\User;
use App\Services\TwoFactor;
use App\Support\ConfigOverlay;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    PlatformTestimonial::query()->delete();   // (rolled back after each test)
    RateLimiter::clear('testimonial-submit:127.0.0.1');
});
afterEach(fn () => ConfigOverlay::refresh());

function tmSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

test('anyone can submit a testimonial on the public page; it waits as pending', function () {
    $this->get('/testimonials/new')->assertOk()->assertSee('How has Olux helped your business?');

    Livewire::test(TestimonialSubmitPage::class)
        ->set('name', 'Ada Lovelace')->set('role', 'Owner, Bloom Salon')
        ->set('quote', 'Bookings and invoices finally live in one place. A real time-saver.')
        ->set('rating', '5')->set('email', 'ada@example.com')
        ->call('submit')->assertHasNoErrors()->assertSet('sent', true)->assertSee('Thank you!');

    $t = PlatformTestimonial::sole();
    expect($t->status)->toBe('pending')->and($t->source)->toBe('public')->and($t->rating)->toBe(5);

    // Not on the landing page (or the API) until an admin publishes it.
    Livewire::withoutLazyLoading()->test(LandingTestimonials::class)->assertDontSee('Bookings and invoices finally live in one place');
    expect($this->getJson('/api/testimonials')->json('meta.count'))->toBe(0);
});

test('the public form validates, drops honeypot spam, and rate-limits', function () {
    Livewire::test(TestimonialSubmitPage::class)->set('name', '')->set('quote', 'short')->call('submit')
        ->assertHasErrors(['name', 'quote']);

    Livewire::test(TestimonialSubmitPage::class)
        ->set('name', 'Bot')->set('quote', str_repeat('buy cheap things ', 3))->set('website', 'http://spam.test')
        ->call('submit')->assertSet('sent', true);
    expect(PlatformTestimonial::count())->toBe(0);

    foreach (range(1, 3) as $i) {
        Livewire::test(TestimonialSubmitPage::class)->set('name', "Person {$i}")->set('quote', 'A genuinely lovely product to use every day.')->call('submit')->assertHasNoErrors();
    }
    Livewire::test(TestimonialSubmitPage::class)->set('name', 'Fourth')->set('quote', 'A genuinely lovely product to use every day.')->call('submit')->assertHasErrors('quote');
    expect(PlatformTestimonial::count())->toBe(3);
});

test('admins add, edit, publish, hide, reorder and delete; only super admins reach the page', function () {
    $this->actingAs(User::factory()->create())->get('/admin/testimonials')->assertForbidden();

    $pending = PlatformTestimonial::create(['name' => 'Pat', 'quote' => 'Pending words for review here.', 'status' => 'pending']);
    $page = Livewire::actingAs(tmSuper())->test(PlatformTestimonialsPage::class)
        ->assertSet('tab', 'all')->assertSee('Pending words for review here.')->assertSee('Approve')
        ->call('setStatus', $pending->id, 'published');
    expect($pending->fresh()->status)->toBe('published')->and($pending->fresh()->published_at)->not->toBeNull();

    $page->call('create')
        ->set('form.name', 'Sam Admin')->set('form.role', 'Founder')->set('form.quote', 'Added straight from the admin panel.')
        ->set('form.rating', '4')->set('form.status', 'published')
        ->call('save')->assertHasNoErrors();
    $added = PlatformTestimonial::where('name', 'Sam Admin')->sole();
    expect($added->source)->toBe('admin')->and($added->rating)->toBe(4);

    $page->call('edit', $added->id)->set('form.quote', 'Edited by the admin team today.')->call('save');
    expect($added->fresh()->quote)->toBe('Edited by the admin team today.');

    // Reorder: Sam moves above Pat.
    $page->call('move', $added->id, -1);
    expect(PlatformTestimonial::live()->pluck('name')->all())->toBe(['Sam Admin', 'Pat']);

    $page->call('setStatus', $pending->id, 'hidden');
    expect(PlatformTestimonial::live()->pluck('name')->all())->toBe(['Sam Admin']);

    $page->call('delete', $added->id);
    expect(PlatformTestimonial::find($added->id))->toBeNull();

    // A non-admin can't call the actions either.
    $other = Livewire::actingAs(tmSuper())->test(PlatformTestimonialsPage::class);
    $this->actingAs(User::factory()->create());
    $other->call('delete', $pending->id)->assertForbidden();
});

test('GET /api/testimonials returns published testimonials only, in admin order, limited (default 5), without private fields', function () {
    foreach (range(1, 7) as $i) {
        PlatformTestimonial::create(['name' => "Customer {$i}", 'quote' => "Quote number {$i} about Olux.", 'status' => 'published', 'position' => $i,
            'published_at' => now(), 'email' => "c{$i}@private.test", 'ip_address' => '10.0.0.'.$i, 'rating' => 5]);
    }
    PlatformTestimonial::create(['name' => 'Waiting', 'quote' => 'Still pending review.', 'status' => 'pending']);

    $res = $this->getJson('/api/testimonials')->assertOk()->json();
    expect(collect($res['data'])->pluck('name')->all())->toBe(['Customer 1', 'Customer 2', 'Customer 3', 'Customer 4', 'Customer 5'])
        ->and($res['meta'])->toMatchArray(['count' => 5, 'limit' => 5, 'total_published' => 7])
        ->and($res['data'][0])->toHaveKeys(['name', 'role', 'quote', 'rating', 'initials'])
        ->and($res['data'][0])->not->toHaveKey('email')->not->toHaveKey('ip_address')
        ->and(json_encode($res))->not->toContain('private.test')->not->toContain('Still pending');

    expect($this->getJson('/api/testimonials?limit=2')->json('meta.count'))->toBe(2);
    $this->getJson('/api/testimonials?limit=99')->assertStatus(422);

    // The admin's landing count is the default for both the API and the landing carousel.
    Livewire::actingAs(tmSuper())->test(PlatformTestimonialsPage::class)->set('landingCount', 3)->call('saveLandingCount')->assertHasNoErrors();
    ConfigOverlay::refresh();
    expect($this->getJson('/api/testimonials')->json('meta.limit'))->toBe(3);
    Livewire::withoutLazyLoading()->test(LandingTestimonials::class)->assertSee('Quote number 3 about Olux.')->assertDontSee('Quote number 4 about Olux.')
        ->assertDontSee('Illustrative examples');
    Livewire::withoutLazyLoading()->test(LandingTestimonials::class, ['limit' => 6])->assertSee('Quote number 6 about Olux.');
    PlatformSetting::where('key', 'landing.testimonials_count')->delete();
});

test('the landing page loads the carousel as a lazy Livewire island; with nothing published it shows the illustrative quotes', function () {
    $this->get('/')->assertOk()->assertSee('Loading testimonials')->assertSee('Share your experience');
    Livewire::withoutLazyLoading()->test(LandingTestimonials::class)->assertSee('Illustrative examples')->assertSee('Tunde R.');
});

test('the admin page lists every testimonial by default — pending first, then published, then hidden', function () {
    PlatformTestimonial::create(['name' => 'Hidden Hal', 'quote' => 'Hidden words for the list.', 'status' => 'hidden']);
    PlatformTestimonial::create(['name' => 'Live Liv', 'quote' => 'Published words for the list.', 'status' => 'published', 'published_at' => now()]);
    PlatformTestimonial::create(['name' => 'Pending Pat', 'quote' => 'Pending words for the list.', 'status' => 'pending']);

    $html = Livewire::actingAs(tmSuper())->test(PlatformTestimonialsPage::class)->assertSet('tab', 'all')->html();
    expect(strpos($html, 'Pending Pat'))->toBeLessThan(strpos($html, 'Live Liv'))
        ->and(strpos($html, 'Live Liv'))->toBeLessThan(strpos($html, 'Hidden Hal'));
    expect($html)->toContain('Approve')->toContain('Edit')->toContain('Delete');
});
