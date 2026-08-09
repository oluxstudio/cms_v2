<?php
use App\Livewire\SubscriptionPage;
use App\Models\User;
use Livewire\Livewire;

test('subscription page renders and the plan detail opens', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user)->test(SubscriptionPage::class)
        ->assertSee('Your subscription')
        ->assertSee('Pro')
        ->call('viewPlan', 'pro')
        ->assertSet('viewingPlan', 'pro')
        ->assertSee('About this plan')
        ->call('closePlan')
        ->assertSet('viewingPlan', null);
});
