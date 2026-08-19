<?php

use App\Livewire\SiteFormsPage;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function formsPageSite(): array
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    $contact = Form::create(['site_id' => $site->id, 'name' => 'contact', 'title' => 'Contact', 'is_active' => true, 'fields' => []]);
    $quote = Form::create(['site_id' => $site->id, 'name' => 'quote', 'title' => 'Quote', 'is_active' => true, 'fields' => []]);
    $read = FormResponse::create(['form_id' => $quote->id, 'fields' => ['email' => 'old@x.test'], 'read_at' => now()]);
    $unread = FormResponse::create(['form_id' => $contact->id, 'fields' => ['name' => 'Ada', 'email' => 'ada@x.test']]);

    return [$user, $site, $contact, $quote, $unread];
}

test('the list shows recent responses and surfaces forms with new responses first', function () {
    [$user, $site, $contact, $quote, $unread] = formsPageSite();

    $lw = Livewire::actingAs($user)->test(SiteFormsPage::class, ['site' => $site])
        ->assertSee('Recent responses')
        ->assertSee('Ada')
        ->assertSee('1 new')
        ->assertSeeInOrder(['Contact', 'Quote']); // unread form first

    // Clicking a recent response jumps into it and marks it read.
    $lw->call('openResponse', $unread->id)
        ->assertSet('mode', 'responses')
        ->assertSet('activeFormId', $contact->id)
        ->assertSet('openId', $unread->id);
    expect($unread->fresh()->read_at)->not->toBeNull();
});

test('cross-site responses never appear and cannot be opened', function () {
    [$user, $site] = formsPageSite();
    $other = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $foreignForm = Form::create(['site_id' => $other->id, 'name' => 'x', 'title' => 'X', 'is_active' => true, 'fields' => []]);
    $foreign = FormResponse::create(['form_id' => $foreignForm->id, 'fields' => ['name' => 'FOREIGN-ROW']]);

    Livewire::actingAs($user)->test(SiteFormsPage::class, ['site' => $site])
        ->assertDontSee('FOREIGN-ROW')
        ->call('openResponse', $foreign->id)
        ->assertSet('mode', 'list');
    expect($foreign->fresh()->read_at)->toBeNull();
});
