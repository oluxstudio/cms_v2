<?php

use App\Models\ApiToken;
use App\Models\Collection;
use App\Models\Component;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

test('the components API links a component to its data-source collection', function () {
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'ds-'.uniqid(),
        'domain' => 'ds.test', 'owner' => $owner->name, 'description' => 'test']);
    $raw = Str::random(64);
    ApiToken::create(['user_id' => $owner->id, 'name' => 'test', 'token' => hash('sha256', $raw)]);
    $auth = ['Authorization' => 'Bearer '.$raw];
    $services = Collection::create(['site_id' => $site->id, 'name' => 'Services', 'slug' => 'services', 'type' => 'grid', 'fields' => []]);

    // Create linked.
    $id = $this->postJson("/api/sites/{$site->name}/components", [
        'name' => 'section-services', 'collection_id' => $services->id,
    ], $auth)->assertStatus(201)
        ->assertJsonPath('component.collection_id', $services->id)
        ->json('component.id');

    // Updates without collection_id leave the link alone.
    $this->patchJson("/api/sites/{$site->name}/components/{$id}", ['description' => 'x'], $auth)
        ->assertOk()->assertJsonPath('component.collection_id', $services->id);

    // Another site's collection is refused.
    $otherSite = Site::create(['user_id' => $owner->id, 'name' => 'ds-other-'.uniqid(),
        'domain' => 'other.test', 'owner' => $owner->name, 'description' => 'test']);
    $foreign = Collection::create(['site_id' => $otherSite->id, 'name' => 'Team', 'slug' => 'team', 'type' => 'grid', 'fields' => []]);
    $this->patchJson("/api/sites/{$site->name}/components/{$id}", ['collection_id' => $foreign->id], $auth)
        ->assertStatus(422);
    expect(Component::find($id)->collection_id)->toBe($services->id);

    // Null unlinks.
    $this->patchJson("/api/sites/{$site->name}/components/{$id}", ['collection_id' => null], $auth)
        ->assertOk()->assertJsonPath('component.collection_id', null);
});
