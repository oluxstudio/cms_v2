<?php

use App\Livewire\ConnectReviewPage;
use App\Models\ApiToken;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\ContentVersion;
use App\Models\Node;
use App\Models\Site;
use App\Models\User;
use App\Services\ContentVersioner;
use Illuminate\Support\Str;
use Livewire\Livewire;

function versionSite(): array
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    $hero = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 't', 'source' => 'api']);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Heading', 'type' => 'text', 'value' => 'One', 'order' => 0]);
    $cta = Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Cta', 'type' => 'text', 'value' => '', 'order' => 1]);
    Node::create(['component_id' => $hero->id, 'parent' => $cta->id, 'label' => 'Label', 'type' => 'text', 'value' => 'Book', 'order' => 0]);

    return [$user, $site, $hero];
}

test('saving a component captures history and revert restores it, nested nodes included', function () {
    [$user, $site, $hero] = versionSite();

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $hero->id)
        ->set('edit.nodes.0.value', 'Two')
        ->call('saveComponent');

    $v = ContentVersion::where('subject_id', $hero->id)->first();
    expect($v)->not->toBeNull()
        ->and($v->payload['nodes'][0]['value'])->toBe('One');

    $lw->call('revertTo', $v->id);
    expect($hero->nodes()->where('label', 'Heading')->value('value'))->toBe('One')
        // the nested cta.label survived the restore round-trip
        ->and($hero->fresh()->nodes()->where('label', 'Label')->value('value'))->toBe('Book');

    // The revert captured the pre-revert state, so it is itself revertible.
    expect(ContentVersion::where('subject_id', $hero->id)->where('created_by', 'pre-revert')->exists())->toBeTrue();
});

test('history is pruned to the configured keep count and skips identical payloads', function () {
    [$user, $site, $hero] = versionSite();
    config(['site_connect.versions_keep' => 3]);
    $versioner = app(ContentVersioner::class);

    $versioner->capture($hero);
    $versioner->capture($hero); // identical — skipped
    expect(ContentVersion::where('subject_id', $hero->id)->count())->toBe(1);

    foreach (range(1, 5) as $i) {
        $hero->nodes()->where('label', 'Heading')->update(['value' => "v$i"]);
        $versioner->capture($hero);
    }
    expect(ContentVersion::where('subject_id', $hero->id)->count())->toBe(3);
});

test('collection revert restores removed items', function () {
    [$user, $site] = versionSite();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Services', 'slug' => 'services', 'type' => 'grid',
        'is_public' => true, 'fields' => [['key' => 'title', 'name' => 'title', 'label' => 'Title', 'type' => 'text']]]);
    $item = CollectionItem::create(['collection_id' => $col->id, 'site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'Cut']]);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('inlineItemRemove', $col->id, null, $item->id);
    expect($col->items()->count())->toBe(0);

    $v = ContentVersion::where('subject_id', $col->id)->orderByDesc('id')->first();
    $lw->call('revertTo', $v->id);
    expect($col->items()->count())->toBe(1)
        ->and($col->items()->first()->data['title'])->toBe('Cut');
});

test('the API update path captures history too', function () {
    [$user, $site, $hero] = versionSite();
    $raw = Str::random(60);
    ApiToken::create(['user_id' => $user->id, 'site_id' => $site->id, 'name' => 't',
        'token' => hash('sha256', $raw), 'token_preview' => 'x', 'abilities' => ['components.manage']]);

    $this->withToken($raw)->patchJson("/api/sites/{$site->name}/components/{$hero->id}", [
        'nodes' => [['label' => 'Heading', 'type' => 'text', 'value' => 'API changed']],
    ])->assertOk();

    $v = ContentVersion::where('subject_id', $hero->id)->first();
    expect($v->payload['nodes'][0]['value'])->toBe('One');
});

test('revert is permission-gated and versions are site-scoped', function () {
    [$user, $site, $hero] = versionSite();
    app(ContentVersioner::class)->capture($hero);
    $v = ContentVersion::where('subject_id', $hero->id)->first();

    $viewer = User::factory()->create();
    $site->members()->attach($viewer->id, ['role' => 'viewer']);
    Livewire::actingAs($viewer)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('revertTo', $v->id)->assertForbidden();

    // A version belonging to another site 404s.
    $other = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    Livewire::actingAs($other->user ?? User::find($other->user_id))
        ->test(ConnectReviewPage::class, ['site' => $other])
        ->call('revertTo', $v->id)->assertNotFound();
});
