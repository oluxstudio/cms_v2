<?php

use App\Livewire\MessagesPage;
use App\Models\AccountMember;
use App\Models\Alert;
use App\Models\Message;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function messagingSite(): array
{
    $owner = User::factory()->create(['name' => 'Olive Owner']);
    $site = Site::create(['user_id' => $owner->id, 'name' => 'msg-'.uniqid(), 'domain' => 'msg-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    $roles = Role::forAccount($owner);
    $admin = $roles->firstWhere('slug', 'admin');

    $memberA = User::factory()->create(['name' => 'Amy Admin']);
    $memberB = User::factory()->create(['name' => 'Ben Builder']);
    // Real invite flow creates ONLY AccountMember rows (no legacy site_user pivot).
    foreach ([$memberA, $memberB] as $m) {
        AccountMember::create(['account_id' => $owner->id, 'user_id' => $m->id, 'role_id' => $admin->id, 'site_id' => $site->id]);
    }

    return [$site, $owner, $memberA, $memberB];
}

test('broadcast unread state is tracked per member, not globally', function () {
    [$site, $owner, $a, $b] = messagingSite();

    Livewire::actingAs($owner)->test(MessagesPage::class, ['siteId' => $site->id])
        ->set('body', 'Team meeting at 3pm')->call('send');

    expect(Message::unreadCountFor($site, $a))->toBe(1)
        ->and(Message::unreadCountFor($site, $b))->toBe(1)
        ->and(Message::unreadCountFor($site, $owner))->toBe(0); // own message

    // A opens the team thread → read for A only.
    Livewire::actingAs($a)->test(MessagesPage::class, ['siteId' => $site->id]);
    expect(Message::unreadCountFor($site, $a))->toBe(0)
        ->and(Message::unreadCountFor($site, $b))->toBe(1);
});

test('a DM thread shows both directions and notifies the recipient once per hour', function () {
    [$site, $owner, $a, $b] = messagingSite();

    Livewire::actingAs($owner)->test(MessagesPage::class, ['siteId' => $site->id])
        ->call('openThread', (string) $a->id)
        ->set('body', 'Hi Amy — can you update the hero?')->call('send')
        ->set('body', 'Also the pricing table please')->call('send');

    // Targeted alert for Amy, deduped (two sends → one alert).
    $alerts = Alert::where('site_id', $site->id)->where('type', 'message')->where('user_id', $a->id)->get();
    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->title)->toContain('Olive Owner');

    // Amy sees both, replies; owner's thread shows all three.
    $lw = Livewire::actingAs($a)->test(MessagesPage::class, ['siteId' => $site->id])
        ->call('openThread', (string) $owner->id);
    expect($lw->instance()->threadMessages)->toHaveCount(2);
    $lw->set('body', 'On it!')->call('send');

    $ownerThread = Livewire::actingAs($owner)->test(MessagesPage::class, ['siteId' => $site->id])
        ->call('openThread', (string) $a->id)->instance()->threadMessages;
    expect($ownerThread)->toHaveCount(3)
        // B can't see the DM at all.
        ->and(Message::unreadCountFor($site, $b))->toBe(0);
});

test('messages carry the sender role for this site and the page names the account', function () {
    [$site, $owner, $a, $b] = messagingSite();
    $site->setAttr('business_name', 'Glow Salon');

    $labels = Livewire::actingAs($owner)->test(MessagesPage::class, ['siteId' => $site->id])
        ->instance()->roleLabels;
    expect($labels[$owner->id])->toBe('Owner')
        ->and($labels[$a->id])->toBe('Admin'); // RBAC role name wins over legacy pivot

    Livewire::actingAs($owner)->test(MessagesPage::class, ['siteId' => $site->id])
        ->assertSee('Glow Salon');
});

test('messages permissions gate the page and sending', function () {
    [$site, $owner, $a, $b] = messagingSite();

    // A role with NO messages.view: page 403s for that member.
    $muted = Role::create(['account_id' => $owner->id, 'name' => 'Muted', 'slug' => 'muted-'.uniqid(), 'permissions' => ['pages.view'], 'is_system' => false]);
    AccountMember::where('user_id', $b->id)->where('account_id', $owner->id)->update(['role_id' => $muted->id]);
    $this->actingAs($b)->get('/'.$site->name.'/messages')->assertForbidden();

    // View without send: page opens, send blocked.
    $reader = Role::create(['account_id' => $owner->id, 'name' => 'Reader', 'slug' => 'reader-'.uniqid(), 'permissions' => ['messages.view'], 'is_system' => false]);
    AccountMember::where('user_id', $b->id)->where('account_id', $owner->id)->update(['role_id' => $reader->id]);
    // fresh() — membershipFor memoizes per model instance.
    $this->actingAs($b = $b->fresh())->get('/'.$site->name.'/messages')->assertOk();
    Livewire::actingAs($b->fresh())->test(MessagesPage::class, ['siteId' => $site->id])
        ->set('body', 'should fail')->call('send')
        ->assertStatus(403);
    expect(Message::where('site_id', $site->id)->count())->toBe(0);
});

test('the other-inboxes strip lists the sites a user can reach with unread counts', function () {
    [$site, $owner, $a, $b] = messagingSite();
    // Amy also owns her own account/site.
    $ownSite = Site::create(['user_id' => $a->id, 'name' => 'own-'.uniqid(), 'domain' => 'own-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $ownSite->members()->syncWithoutDetaching([$a->id => ['role' => 'owner']]);

    // Unread broadcast waiting for Amy on the team site.
    $site->messages()->create(['sender_id' => $owner->id, 'recipient_id' => null, 'body' => 'ping']);

    // Viewing her OWN site's inbox, the team site shows up with 1 unread.
    $inboxes = Livewire::actingAs($a)->test(MessagesPage::class, ['siteId' => $ownSite->id])
        ->instance()->otherInboxes;
    $entry = collect($inboxes)->firstWhere('name', $site->name);
    expect($entry)->not->toBeNull()
        ->and($entry['unread'])->toBe(1);
});
