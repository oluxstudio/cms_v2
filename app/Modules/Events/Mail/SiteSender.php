<?php

namespace App\Modules\Events\Mail;

use App\Models\Site;
use Illuminate\Mail\Mailables\Address;

/** Envelope parts: From name = the site (business) name, Reply-To = the site's email (Site Properties). */
trait SiteSender
{
    protected function sender(Site $site): array
    {
        $parts = $site->mailSender();
        if (! isset($parts['from']) && filled(config('mail.from.address'))) {
            $parts['from'] = new Address((string) config('mail.from.address'), self::siteLabel($site));
        }

        return $parts;
    }

    public static function siteLabel(Site $site): string
    {
        return (string) ($site->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $site->name)));
    }
}
