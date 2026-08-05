<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Shared media-upload path: stores a file on the public disk under the site's
 * media folder and records it. Used by the Media page and the MediaPicker
 * modal so uploads behave identically everywhere.
 */
class MediaStore
{
    public function store(Site $site, UploadedFile $file): Media
    {
        $path = $file->store('media/'.$site->name, 'public');

        return Media::create([
            'site_id'   => $site->id,
            'name'      => $file->getClientOriginalName(),
            'file_type' => Media::typeFromMime($file->getMimeType()),
            'url'       => Storage::url($path),
            'size'      => Media::humanSize((int) $file->getSize()),
            'alt_text'  => null,
        ]);
    }
}
