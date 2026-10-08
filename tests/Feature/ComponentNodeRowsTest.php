<?php

use App\Livewire\ConnectReviewPage;
use App\Models\Component;
use App\Models\Node;
use App\Models\Site;
use App\Models\User;
use App\Services\TemplateScaffolder;
use Livewire\Livewire;

// Repeatable rows in a block ("Slide 1 Image", "Slide 1 Caption" … — a
// carousel's slides): the component panel adds, removes and reorders whole
// rows, and template refreshes never bring back rows the owner removed.

function slideDefs(int $rows): array
{
    $nodes = [['label' => 'Gallery Title', 'type' => 'text', 'value' => 'Photo gallery']];
    for ($n = 1; $n <= $rows; $n++) {
        $nodes[] = ['label' => "Slide {$n} Image", 'type' => 'image', 'value' => "/assets/images/g{$n}.jpg", 'description' => 'item:img'];
        $nodes[] = ['label' => "Slide {$n} Caption", 'type' => 'text', 'value' => "Caption {$n}", 'description' => 'item:caption'];
    }

    return [['url' => '/mens-ministry', 'name' => 'Mens Ministry', 'blocks' => [['name' => 'Ministry Photos', 'perPage' => true, 'nodes' => $nodes]]]];
}

function slideRows(Component $c): array
{
    return $c->nodes()->get()->filter(fn ($n) => str_starts_with($n->label, 'Slide '))
        ->sortBy('label', SORT_NATURAL)->mapWithKeys(fn ($n) => [$n->label => $n->value])->all();
}

it('adds, reorders and removes whole rows from the component panel', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id, 'domain' => 'rows-'.uniqid().'.test']);
    app(TemplateScaffolder::class)->applyPages($site, slideDefs(3));
    $photos = Component::where('site_id', $site->id)->where('name', 'Ministry Photos')->firstOrFail();

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $photos->id)
        ->assertSee('Slides')->assertSee('+ Add slide');

    // Add a 4th slide and fill it, move it up, remove slide 1, save.
    $lw->call('addNodeRow', 'Slide');
    $nodes = $lw->get('edit.nodes');
    $img = collect($nodes)->search(fn ($n) => $n['label'] === 'Slide 4 Image');
    $cap = collect($nodes)->search(fn ($n) => $n['label'] === 'Slide 4 Caption');
    expect($img)->not->toBeFalse()->and($nodes[$img]['type'])->toBe('image');
    $lw->set("edit.nodes.$img.value", '/assets/images/new.jpg')->set("edit.nodes.$cap.value", 'New one')
        ->call('moveNodeRow', 'Slide', 4, -1)
        ->call('removeNodeRow', 'Slide', 1)
        ->call('saveComponent');

    expect(slideRows($photos))->toBe([
        'Slide 1 Caption' => 'Caption 2', 'Slide 1 Image' => '/assets/images/g2.jpg',
        'Slide 2 Caption' => 'New one', 'Slide 2 Image' => '/assets/images/new.jpg',
        'Slide 3 Caption' => 'Caption 3', 'Slide 3 Image' => '/assets/images/g3.jpg',
    ]);
    expect(Node::where('component_id', $photos->id)->where('label', 'like', 'Slide 4%')->exists())->toBeFalse();

    // The last row can't be removed (an empty group would show the template's rows).
    $lw->call('removeNodeRow', 'Slide', 1)->call('removeNodeRow', 'Slide', 1)->call('removeNodeRow', 'Slide', 1)->call('saveComponent');
    expect(slideRows($photos))->toHaveCount(2);
});

it('never re-creates rows the owner removed when the template refreshes', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'rows-'.uniqid().'.test']);
    $scaffold = app(TemplateScaffolder::class);
    $scaffold->applyPages($site, slideDefs(4));
    $photos = Component::where('site_id', $site->id)->where('name', 'Ministry Photos')->firstOrFail();
    $photos->nodes()->where('label', 'like', 'Slide 3 %')->delete();
    $photos->nodes()->where('label', 'like', 'Slide 4 %')->delete();

    $scaffold->applyPages($site, slideDefs(4));
    expect(array_keys(slideRows($photos)))->toBe(['Slide 1 Caption', 'Slide 1 Image', 'Slide 2 Caption', 'Slide 2 Image']);

    // A component without any rows yet still gets the template's rows.
    $photos->nodes()->where('label', 'like', 'Slide %')->delete();
    $scaffold->applyPages($site, slideDefs(2));
    expect(slideRows($photos))->toHaveCount(4);
});
