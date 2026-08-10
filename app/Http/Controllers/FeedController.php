<?php

namespace App\Http\Controllers;

use App\Models\Site;

class FeedController extends Controller
{
    public function index(string $siteName)
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        $config = $site->feature('twitter');

        return view('public.feed', [
            'site' => $site,
            'handle' => ltrim(trim((string) ($config['handle'] ?? '')), '@'),
            'theme' => $config['theme'] ?? 'light',
            'count' => (int) ($config['count'] ?? 5),
        ]);
    }
}
