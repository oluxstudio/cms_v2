<?php

use App\Livewire\SiteDashboard;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guests see the landing page with pricing and auth links', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Sign in')
        ->assertSee('Most popular')          // pricing rendered from config/plans.php
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false);
});

test('the landing page shows for everyone at the root', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertOk()->assertSee('Open app');
});

test('the app home lives at /select-site behind auth', function () {
    $this->get('/select-site')->assertRedirect('/login');

    $this->actingAs(User::factory()->create());
    $this->get('/select-site')->assertStatus(200);
});

test('commerce tiles only appear when the feature is on and there is data', function () {
    $user = User::factory()->create();
    $site = Site::create(['user_id' => $user->id, 'name' => 'dash-'.uniqid(), 'domain' => 'dash-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

    // Fresh site → no commerce tiles at all, but the activity card lists forms.
    $c = Livewire::actingAs($user)->test(SiteDashboard::class, ['site' => $site]);
    expect($c->get('commerceTiles'))->toBe([]);
    $c->assertSee('Forms')->assertDontSee('Invoices overdue');

    $site->enableFeature('invoices');
    Invoice::create(['site_id' => $site->id, 'number' => 'INV-1', 'customer_name' => 'A', 'customer_email' => 'a@example.com', 'currency' => 'gbp', 'status' => 'sent', 'items' => [['description' => 'Cut', 'qty' => 1, 'unit_cents' => 1000]], 'subtotal_cents' => 1000, 'total_cents' => 1000, 'due_date' => now()->subDays(3)]);

    $c = Livewire::actingAs($user)->test(SiteDashboard::class, ['site' => $site]);
    $labels = collect($c->get('commerceTiles'))->pluck('label')->all();
    expect($labels)->toContain('Invoices sent')->toContain('Invoices overdue')->not->toContain('Orders');
    $c->assertSee('Invoices overdue')->assertSee('Needs chasing');
});

test('the dashboard shows the vertical pack matching the business type', function () {
    $user = User::factory()->create();
    $mk = function (string $type) use ($user) {
        $site = Site::create(['user_id' => $user->id, 'name' => 'vp-'.uniqid(), 'domain' => 'vp-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
        $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
        if ($type !== '') {
            $site->setAttr('business_type', $type);
        }

        return $site;
    };

    Livewire::actingAs($user)->test(SiteDashboard::class, ['site' => $mk('barber')])->assertSee('Salon pulse');
    Livewire::actingAs($user)->test(SiteDashboard::class, ['site' => $mk('plumber')])->assertSee('Jobs & quotes');
    Livewire::actingAs($user)->test(SiteDashboard::class, ['site' => $mk('')])->assertDontSee('Salon pulse')->assertDontSee('Jobs & quotes');
});
