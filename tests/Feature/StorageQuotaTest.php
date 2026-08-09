<?php

use App\Livewire\MediaPage;
use App\Models\Media;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function quotaAccount(string $plan = 'trial'): array
{
    $owner = User::factory()->create();
    $owner->currentSubscription()->update(['plan' => $plan, 'status' => $plan === 'trial' ? 'trialing' : 'active']);
    $site = Site::create(['user_id' => $owner->id, 'name' => 'sq-'.uniqid(), 'domain' => 'sq.test', 'owner' => $owner->name, 'description' => 't']);

    return [$owner, $site];
}

test('storage limit comes from the plan config and usage sums media bytes', function () {
    [$owner, $site] = quotaAccount('trial');       // 20 MB
    expect($owner->currentSubscription()->storageLimitMb())->toBe(20);

    Media::create(['site_id' => $site->id, 'name' => 'a.png', 'file_type' => 'image', 'url' => '/storage/x.png', 'size' => '5 MB', 'bytes' => 5 * 1024 * 1024]);
    Media::create(['site_id' => $site->id, 'name' => 'b.png', 'file_type' => 'image', 'url' => '/storage/y.png', 'size' => '3 MB', 'bytes' => 3 * 1024 * 1024]);

    expect($owner->currentSubscription()->storageUsedBytes())->toBe(8 * 1024 * 1024)
        ->and($owner->currentSubscription()->storageRemainingBytes())->toBe(12 * 1024 * 1024);

    // Enterprise = unlimited.
    [$ent] = quotaAccount('enterprise');
    expect($ent->currentSubscription()->storageLimitBytes())->toBeNull()
        ->and($ent->currentSubscription()->canStore(999_999_999))->toBeTrue();
});

test('an upload that exceeds the plan quota is skipped with an upgrade prompt', function () {
    Storage::fake('public');
    [$owner, $site] = quotaAccount('trial');       // 20 MB cap
    // Fill 18 MB already used.
    Media::create(['site_id' => $site->id, 'name' => 'big.png', 'file_type' => 'image', 'url' => '/storage/big.png', 'size' => '18 MB', 'bytes' => 18 * 1024 * 1024]);

    $before = $site->media()->count();
    Livewire::actingAs($owner)->test(MediaPage::class, ['site' => $site])
        ->set('uploads', [UploadedFile::fake()->create('extra.bin', 5 * 1024)]) // 5 MB > 2 MB free
        ->assertDispatched('upgrade-required');

    expect($site->media()->count())->toBe($before); // nothing stored
});

test('an upload within the quota succeeds', function () {
    Storage::fake('public');
    [$owner, $site] = quotaAccount('pro');          // 5 GB

    Livewire::actingAs($owner)->test(MediaPage::class, ['site' => $site])
        ->set('uploads', [UploadedFile::fake()->create('ok.bin', 2 * 1024)]) // 2 MB
        ->assertNotDispatched('upgrade-required');

    expect($site->media()->count())->toBe(1)
        ->and($site->media()->first()->bytes)->toBeGreaterThan(0);
});
