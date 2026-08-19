<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\SiteConnect\SiteExporter;
use Illuminate\Support\Facades\Auth;

/**
 * Site Connect export: streams the transformed-site zip (attributed HTML +
 * connect.js). The in-CMS preview is now the inline ConnectReviewPage Livewire
 * screen (no iframe), so the old replica/content iframe renderers are gone.
 */
class ConnectPreviewController extends Controller
{
    /** Download the transformed-site export (attributed HTML + connect.js) as a zip. */
    public function export(string $siteID, SiteExporter $exporter)
    {
        $site = Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->allows(Auth::user(), 'publish.manage'), 403);

        $result = $exporter->export($site);
        abort_if($result['pages'] === 0, 404, 'Nothing committed to export yet.');

        return response()->download($result['path'], $site->name.'-site-connect.zip')
            ->deleteFileAfterSend(true);
    }
}
