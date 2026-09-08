<?php

use App\Livewire\BookingsPage;
use App\Models\Booking;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;

function noShowSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'ns-'.uniqid(), 'domain' => 'ns-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->enableFeature('bookings');

    return [$owner, $site];
}

test('a past booking can be marked no-show, a future one cannot, and no customer email is sent', function () {
    Mail::fake();
    [$owner, $site] = noShowSite();
    $past = Booking::create(['site_id' => $site->id, 'customer_name' => 'Ghost', 'customer_email' => 'g@x.test', 'status' => 'confirmed', 'starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2)]);
    $future = Booking::create(['site_id' => $site->id, 'customer_name' => 'Keen', 'customer_email' => 'k@x.test', 'status' => 'confirmed', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);

    $page = Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site]);
    $page->call('setStatus', $past->id, 'no_show');
    $page->call('setStatus', $future->id, 'no_show');

    expect($past->fresh()->status)->toBe('no_show')
        ->and($future->fresh()->status)->toBe('confirmed');
    Mail::assertNothingQueued();
});

test('no-shows drop out of active/upcoming scopes and the model knows every status', function () {
    [, $site] = noShowSite();
    $b = Booking::create(['site_id' => $site->id, 'customer_name' => 'G', 'customer_email' => 'g2@x.test', 'status' => 'confirmed', 'starts_at' => now()->subHour(), 'ends_at' => now()]);
    $b->markNoShow();

    expect(Booking::where('site_id', $site->id)->active()->count())->toBe(0)
        ->and(Booking::STATUSES)->toContain('no_show');
});

test('the admin API accepts no_show', function () {
    [$owner, $site] = noShowSite();
    $b = Booking::create(['site_id' => $site->id, 'customer_name' => 'G', 'customer_email' => 'g3@x.test', 'status' => 'confirmed', 'starts_at' => now()->subHour(), 'ends_at' => now()]);
    $token = $owner->apiTokens()->create(['site_id' => $site->id, 'name' => 't', 'token' => hash('sha256', $plain = Str::random(40)), 'abilities' => null]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->patchJson("/api/sites/{$site->name}/bookings/{$b->id}", ['status' => 'no_show'])
        ->assertSuccessful();
    expect($b->fresh()->status)->toBe('no_show');
})->skip(fn () => ! file_exists(base_path('app/Http/Controllers/Api/BookingAdminApiController.php')), 'no admin api');
