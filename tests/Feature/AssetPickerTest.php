<?php

use App\Livewire\CollectionsPage;
use App\Livewire\MediaPage;
use App\Livewire\MediaPicker;
use App\Livewire\ProductDetailPage;
use App\Livewire\ProductsPage;
use App\Models\Media;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

function pickerSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'pick-'.uniqid(), 'domain' => '',
        'owner' => $owner->name, 'description' => 't',
    ]);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site];
}

test('the picker upload stores the file in the asset library and returns its URL', function () {
    Storage::fake('public');
    [$owner, $site] = pickerSite();

    $res = $this->actingAs($owner)
        ->postJson(route('media.upload', $site->name), ['file' => UploadedFile::fake()->image('hero.jpg')])
        ->assertCreated();

    $media = Media::where('site_id', $site->id)->where('name', 'hero.jpg')->first();
    expect($media)->not->toBeNull()
        ->and($res->json('url'))->toBe($media->publicUrl())
        ->and($res->json('type'))->toBe('image');
});

test('only site members with asset rights can upload through the picker', function () {
    Storage::fake('public');
    [, $site] = pickerSite();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson(route('media.upload', $site->name), ['file' => UploadedFile::fake()->image('x.jpg')])
        ->assertForbidden();
    expect(Media::where('site_id', $site->id)->count())->toBe(0);
});

test('product pictures come from the asset picker URL', function () {
    [$owner, $site] = pickerSite();
    $url = 'https://cdn.example.test/mug.jpg';

    Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site])
        ->call('create')
        ->set('name', 'Mug')->set('price', '9.50')->set('imageUrl', $url)
        ->call('save')
        ->assertHasNoErrors();

    $p = Product::where('site_id', $site->id)->where('name', 'Mug')->firstOrFail();
    expect($p->image)->toBe($url)->and($p->image_url)->toBe($url);

    Livewire::actingAs($owner)->test(ProductDetailPage::class, ['site' => $site, 'product' => $p])
        ->call('edit')
        ->assertSet('imageUrl', $url)
        ->set('imageUrl', '')
        ->call('save');
    expect($p->fresh()->image)->toBeNull();
});

test('older products with a stored upload path still resolve to a URL', function () {
    [, $site] = pickerSite();
    $p = Product::create(['site_id' => $site->id, 'name' => 'Old', 'slug' => 'old', 'price_cents' => 100, 'currency' => 'gbp', 'image' => 'products/old.jpg']);

    expect($p->image_url)->toBe(Storage::url('products/old.jpg'));
});

test('a file uploaded in the editor picker lands in Assets and is picked into the field', function () {
    Storage::fake('public');
    [$owner, $site] = pickerSite();
    $ctx = ['scope' => 'collection-item', 'key' => 'photo'];

    $c = Livewire::actingAs($owner)->test(MediaPicker::class, ['siteId' => $site->id])
        ->call('openPicker', $ctx)
        ->set('uploads', [UploadedFile::fake()->image('team.jpg')]);

    $media = Media::where('site_id', $site->id)->where('name', 'team.jpg')->firstOrFail();
    $c->assertDispatched('media-picked', context: $ctx, mediaRef: $media->ref(), url: $media->url)
        ->assertSet('open', false);
});

test('a gallery picker takes every uploaded file', function () {
    Storage::fake('public');
    [$owner, $site] = pickerSite();

    $c = Livewire::actingAs($owner)->test(MediaPicker::class, ['siteId' => $site->id])
        ->call('openPicker', ['scope' => 'nested-media', 'path' => 'gallery', 'append' => true])
        ->set('uploads', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]);

    expect(Media::where('site_id', $site->id)->count())->toBe(2);
    expect(collect($c->effects['dispatches'] ?? [])->where('name', 'media-picked')->count())->toBe(2);
});

// ── Every picker stores "@media/{filename}"; payloads resolve it ─────────

function pickerMedia(Site $site, string $file = 'abc123.jpg'): Media
{
    return Media::create(['site_id' => $site->id, 'name' => 'Team photo', 'file_type' => 'image',
        'url' => '/storage/media/'.$site->name.'/'.$file, 'size' => '1 KB']);
}

test('the picker upload answers with the @media reference to store', function () {
    Storage::fake('public');
    [$owner, $site] = pickerSite();

    $res = $this->actingAs($owner)
        ->postJson(route('media.upload', $site->name), ['file' => UploadedFile::fake()->image('hero.jpg')])
        ->assertCreated();

    $media = Media::where('site_id', $site->id)->firstOrFail();
    expect($res->json('ref'))->toBe('@media/'.basename($media->url))
        ->and($this->getJson('/api/sites/'.$site->name.'/media')->json('data.0.ref'))->toBe($media->ref());
});

test('collection fields picked in the editor store the @media reference', function () {
    [$owner, $site] = pickerSite();
    $m = pickerMedia($site);

    Livewire::actingAs($owner)->test(CollectionsPage::class, ['site' => $site])
        ->call('onMediaPicked', ['scope' => 'collection-item', 'key' => 'photo'], $m->ref(), $m->url)
        ->assertSet('itemForm.photo', '@media/abc123.jpg');
});

test('@media references resolve to the file location everywhere content is served', function () {
    [, $site] = pickerSite();
    $m = pickerMedia($site);
    $col = $site->collections()->create(['name' => 'Team', 'slug' => '', 'type' => 'grid', 'fields' => [], 'is_public' => true]);
    $col->items()->create(['site_id' => $site->id, 'status' => 'published', 'data' => [
        'photo' => $m->ref(),
        'gallery' => [$m->ref(), 'https://x.test/a.jpg'],
        'rows' => [['img' => $m->ref(), 'label' => 'Hi']],
    ]]);

    $data = $col->fresh()->toApiArray()['items'][0]['data'];
    expect($data['photo'])->toBe($m->url)
        ->and($data['gallery'])->toBe([$m->url, 'https://x.test/a.jpg'])
        ->and($data['rows'][0])->toBe(['img' => $m->url, 'label' => 'Hi']);

    $p = Product::create(['site_id' => $site->id, 'name' => 'Mug', 'slug' => 'mug-'.uniqid(), 'price_cents' => 100, 'currency' => 'gbp', 'image' => $m->ref()]);
    expect($p->image_url)->toBe($m->url);

    $site->setAttr('email.logo', $m->ref());
    expect($site->brandLogo())->toBe(url($m->url)); // mail clients need absolute URLs
});

test('post bodies store library images as @media references and serve real URLs', function () {
    [, $site] = pickerSite();
    $m = pickerMedia($site);
    $html = '<p>Hi</p><img src="'.$m->publicUrl().'"><img src="https://other.test/x.png">';

    $stored = Media::refHtml($site->id, $html);
    expect($stored)->toBe('<p>Hi</p><img src="@media/abc123.jpg"><img src="https://other.test/x.png">')
        ->and(Media::resolveHtml($site->id, $stored))->toBe('<p>Hi</p><img src="'.$m->url.'"><img src="https://other.test/x.png">');
});

test('the Assets dropzone hears when a batch is saved — or rejected — so its progress panel can finish', function () {
    Storage::fake('public');
    [$owner, $site] = pickerSite();

    Livewire::actingAs($owner)->test(MediaPage::class, ['site' => $site])
        ->assertSee('Browse files')->assertSee('Uploading ')
        ->set('uploads', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
        ->assertDispatched('media-uploaded', count: 2, skipped: 0);

    Livewire::actingAs($owner)->test(MediaPage::class, ['site' => $site])
        // Over 50 MB: refused at Livewire's upload step (the browser gets
        // livewire-upload-error) — nothing is saved, nothing reported as added.
        ->set('uploads', [UploadedFile::fake()->create('huge.zip', 60000)])
        ->assertNotDispatched('media-uploaded')
        ->assertHasErrors();
    expect(Media::where('site_id', $site->id)->where('name', 'huge.zip')->exists())->toBeFalse();
});
