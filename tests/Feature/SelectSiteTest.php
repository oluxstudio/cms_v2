<?php
use App\Livewire\SiteComponent;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

test('clicking a site tile switches to that site dashboard', function () {
    $owner = User::factory()->create();
    $site = Site::create(['user_id'=>$owner->id,'name'=>'switch-'.uniqid(),'domain'=>'d.test','owner'=>$owner->name,'description'=>'t']);

    Livewire::actingAs($owner)->test(SiteComponent::class)
        ->call('selected', $site->id)
        ->assertRedirect('/'.$site->name.'/dashboard');
});

test('a user cannot switch to a site they cannot access', function () {
    $owner = User::factory()->create();
    $site = Site::create(['user_id'=>$owner->id,'name'=>'priv-'.uniqid(),'domain'=>'d.test','owner'=>$owner->name,'description'=>'t']);
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)->test(SiteComponent::class)
        ->call('selected', $site->id)
        ->assertStatus(403);
});
