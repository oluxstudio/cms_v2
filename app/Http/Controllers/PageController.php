<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\SiteObject;

class PageController extends Controller
{
    use SiteObject;

    public function index($siteID) {
        $site = $this->getSitebyID($siteID);
        
        if ($site === null) {
            abort(404);
        }
        return view('selected-site',['site' => $site]);
    }
}
