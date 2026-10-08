<?php

namespace App\Http\Controllers;

use App\Exceptions\PlanLimitReached;
use App\Models\Site;
use App\Services\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Upload new" inside the asset picker (<x-asset-picker>). Assets reach a
 * site only through the picker or a URL — an upload here lands in the
 * site's asset library first (plan storage limits apply), and the picker
 * then uses the stored asset's URL like any other pick.
 */
class AssetUploadController extends Controller
{
    public function __invoke(Request $request, string $siteID, MediaStore $store): JsonResponse
    {
        $site = Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->allows(Auth::user(), 'media.manage'), 403, 'You cannot upload assets on this site.');

        $request->validate(['file' => ['required', 'file', 'max:51200']]); // 50 MB, same as the Assets page

        try {
            $media = $store->store($site, $request->file('file'));
        } catch (PlanLimitReached $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $media->id,
            'name' => $media->name,
            'type' => $media->file_type,
            'url' => $media->publicUrl(),
            'ref' => $media->ref(),
        ], 201);
    }
}
