<?php

namespace App\Services;

use App\Exceptions\PlanLimitReached;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Shared media-upload path: stores a file on the public disk under the site's
 * media folder and records it. Used by the Media page and the MediaPicker
 * modal so uploads behave identically everywhere — including the account's
 * storage pool (PlanLimitReached when the file won't fit).
 */
class MediaStore
{
    public function store(Site $site, UploadedFile $file): Media
    {
        $sub = $site->user?->currentSubscription();
        if ($sub && ! $sub->canStore((int) $file->getSize())) {
            throw PlanLimitReached::storage($sub);
        }
        $path = $file->store('media/'.$site->name, 'public');

        return Media::create([
            'site_id' => $site->id,
            'name' => $file->getClientOriginalName(),
            'file_type' => Media::guessType($file->getClientMimeType() ?: $file->getMimeType(), $file->getClientOriginalName()),
            'url' => Storage::url($path),
            'size' => Media::humanSize((int) $file->getSize()),
            'bytes' => (int) $file->getSize(),
            'alt_text' => null,
        ]);
    }
}
