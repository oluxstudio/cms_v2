<?php

namespace App\Modules\Newsletter;

use App\Models\Site;
use App\Support\SiteProperties;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Str;

/**
 * Per-site newsletter settings + the sender identity every newsletter email
 * uses: From = the site's name on the platform address (so SPF/DKIM pass),
 * Reply-To = the site's email.
 */
class Newsletter
{
    public const FEATURE = 'newsletter';

    public static function settings(Site $site): array
    {
        return $site->feature(self::FEATURE);
    }

    /** Double opt-in applies only once the site has installed the Newsletter add-on. */
    public static function doubleOptIn(Site $site): bool
    {
        return $site->hasFeature(self::FEATURE)
            && filter_var(self::settings($site)['double_opt_in'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    public static function siteName(Site $site): string
    {
        return trim(SiteProperties::value($site, 'site_name')) ?: Str::headline($site->name);
    }

    public static function fromName(Site $site): string
    {
        $name = trim((string) (self::settings($site)['from_name'] ?? '')) ?: self::siteName($site);

        return trim(mb_substr(preg_replace('/[\r\n\t"<>]+/', ' ', $name), 0, 80));
    }

    public static function replyTo(Site $site): ?string
    {
        foreach ([self::settings($site)['reply_to'] ?? '', SiteProperties::value($site, 'email')] as $candidate) {
            $candidate = trim((string) $candidate);
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Postal address line for the email footer (CAN-SPAM / PECR). */
    public static function address(Site $site): string
    {
        return collect(['address_street', 'address_town', 'address_county', 'address_postcode', 'address_country'])
            ->map(fn ($k) => trim(SiteProperties::value($site, $k)))
            ->filter()->implode(', ');
    }

    /** Everything an email template needs about the sender — computed once per send batch. */
    public static function brand(Site $site): array
    {
        return [
            'name' => self::siteName($site),
            'from_name' => self::fromName($site),
            'reply_to' => self::replyTo($site),
            'logo' => $site->brandLogo(),
            'address' => self::address($site),
        ];
    }

    /** Envelope parts (spread into `new Envelope(...)`) for a brand array. */
    public static function envelope(array $brand): array
    {
        $out = [];
        if (filled(config('mail.from.address'))) {
            $out['from'] = new Address((string) config('mail.from.address'), $brand['from_name'] ?: null);
        }
        if ($brand['reply_to']) {
            $out['replyTo'] = [new Address($brand['reply_to'], $brand['from_name'] ?: null)];
        }

        return $out;
    }
}
