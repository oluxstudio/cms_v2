<?php

use App\Livewire\MediaPage;
use App\Models\Component;
use App\Models\Media;
use App\Models\Node;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

test('the assets page summarises the library and filters images missing alt text', function () {
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'mps-'.uniqid().'.test']);
    $mk = fn (string $name, string $type, int $bytes, ?string $alt = null) => Media::create([
        'site_id' => $site->id, 'name' => $name, 'file_type' => $type, 'url' => '/storage/media/'.$site->name.'/'.$name,
        'bytes' => $bytes, 'size' => Media::humanSize($bytes), 'alt_text' => $alt,
    ]);
    $used = $mk('hero-'.uniqid().'.jpg', 'image', 2 * 1024 * 1024, 'Hero');
    $noAlt = $mk('team-'.uniqid().'.jpg', 'image', 300 * 1024);
    $orphan = $mk('old-brochure-'.uniqid().'.pdf', 'document', 50 * 1024);

    $c = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'System', 'source' => 'app']);
    Node::create(['component_id' => $c->id, 'label' => 'Image', 'type' => 'image', 'value' => '@media/'.basename($used->url), 'parent' => '0', 'order' => 0]);

    $page = Livewire::actingAs($owner)->test(MediaPage::class, ['site' => $site])
        ->assertViewHas('summary', function ($s) use ($used, $orphan, $noAlt) {
            $unusedIds = collect($s['unused']['sample'])->pluck('id');

            return $s['missingAlt'] === 1                       // the team photo
                && $s['heavy'] === 1                            // the 2 MB hero
                && $s['unused']['count'] === 2                  // hero is referenced by a section; the other two aren't
                && $unusedIds->contains($orphan->id) && $unusedIds->contains($noAlt->id)
                && $s['largest'][0]['id'] === $used->id;
        })
        ->assertSee('Needs attention')->assertSee('without alt text');

    $page->call('showMissingAlt')
        ->assertSet('activeTab', 'image')->assertSet('missingAlt', true)
        ->assertViewHas('mediaItems', fn ($p) => collect($p->items())->pluck('id')->all() === [$noAlt->id]);

    $page->call('setTab', 'all')->assertSet('missingAlt', false);
});
