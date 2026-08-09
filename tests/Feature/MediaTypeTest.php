<?php
use App\Models\Media;
use App\Models\Site;
use App\Models\User;
use App\Services\MediaStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('media type detection buckets svg, fonts and audio correctly', function () {
    // Extension wins where MIME lies (svg often sniffs as text/*).
    expect(Media::guessType('text/plain', 'logo.svg'))->toBe('image')
        ->and(Media::guessType('image/svg+xml', 'logo.svg'))->toBe('image')
        ->and(Media::guessType(null, 'brand.woff2'))->toBe('font')
        ->and(Media::guessType('application/octet-stream', 'Inter.ttf'))->toBe('font')
        ->and(Media::guessType('audio/mpeg', 'jingle.mp3'))->toBe('audio')
        ->and(Media::guessType(null, 'track.wav'))->toBe('audio')
        ->and(Media::guessType('image/png', 'photo.png'))->toBe('image')
        ->and(Media::guessType('video/mp4', 'clip.mp4'))->toBe('video')
        ->and(Media::guessType('application/pdf', 'brief.pdf'))->toBe('document');
});

test('an uploaded svg is stored as an image asset (previewable)', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $site = Site::create(['user_id'=>$owner->id,'name'=>'mt-'.uniqid(),'domain'=>'mt.test','owner'=>$owner->name,'description'=>'t']);

    $svg = UploadedFile::fake()->createWithContent('mark.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    $media = app(MediaStore::class)->store($site, $svg);

    expect($media->file_type)->toBe('image');
});
