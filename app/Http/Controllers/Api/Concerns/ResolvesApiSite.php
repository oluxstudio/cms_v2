<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Site;
use Illuminate\Http\Request;

/**
 * Shared site resolution + authorization for the API controllers.
 *
 * Management writes require ALL of:
 *   1. a valid token (auth.token middleware ran),
 *   2. the token user holding the permission on the site (Site::allows),
 *   3. the token itself being scoped to the site (or unscoped), and
 *   4. the token itself carrying the ability (or having no ability list).
 */
trait ResolvesApiSite
{
    protected function publicSite(string $siteName): Site
    {
        return Site::where('name', $siteName)->firstOrFail();
    }

    protected function manageableSite(Request $request, string $siteName, string $permission): Site
    {
        $site = $this->publicSite($siteName);
        $user = $request->attributes->get('api_token_user');
        $token = $request->attributes->get('api_token');

        abort_unless($user && $site->allows($user, $permission), 403,
            "This token cannot use {$permission} on this site.");
        abort_unless($token === null || $token->allowsSite($site), 403,
            'This token is scoped to a different site.');
        abort_unless($token === null || $token->can($permission), 403,
            "This token does not carry the {$permission} ability.");

        return $site;
    }
}
