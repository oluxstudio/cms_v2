<?php

namespace App\Support;

use App\Models\Service;
use App\Models\Site;

/**
 * The go-live checklist: what this SITE still needs before it's really
 * running,every step auto-detected from real data (same pattern as the
 * account-level Onboarding). Shown on the site dashboard until complete.
 */
class SiteChecklist
{
    /** @return list<array{key:string,label:string,description:string,done:bool,cta_url:string,cta_label:string}> */
    public static function steps(Site $site): array
    {
        $to = fn (string $path) => url($site->name.'/'.$path);
        $sub = $site->user?->currentSubscription();

        $steps = [
            [
                'key' => 'branding',
                'label' => 'Make it yours',
                'description' => 'Add your logo and colours so the site and emails carry your brand.',
                'done' => filled($site->getAttr('email.logo')) || filled($site->theme),
                'cta_url' => $to('emails'),
                'cta_label' => 'Add branding',
            ],
        ];

        if ($site->hasFeature('bookings')) {
            $steps[] = [
                'key' => 'services',
                'label' => 'Add services & prices',
                'description' => 'List what you offer so clients can book it.',
                'done' => Service::where('site_id', $site->id)->exists(),
                'cta_url' => $to('bookings'),
                'cta_label' => 'Add services',
            ];
        }

        $steps[] = [
            'key' => 'payments',
            'label' => 'Connect payments',
            'description' => 'Take deposits, invoice payments and orders online with Stripe.',
            'done' => $site->stripeReady(),
            'cta_url' => $to('marketplace'),
            'cta_label' => 'Connect Stripe',
        ];

        $steps[] = [
            'key' => 'live',
            'label' => 'Put it on your domain',
            'description' => 'Buy or connect a domain — until then you\'re live on your free address.',
            'done' => (bool) ($site->live && $site->domain_verified_at),
            'cta_url' => $to('publish'),
            'cta_label' => 'Go live',
        ];

        $steps[] = [
            'key' => 'plan',
            'label' => 'Pick a plan',
            'description' => 'Choose the plan that fits before your free trial ends.',
            'done' => $sub !== null && $sub->status === 'active' && $sub->plan !== 'trial',
            'cta_url' => route('account.subscription'),
            'cta_label' => 'See plans',
        ];

        return $steps;
    }

    /** @return array{done:int,total:int,complete:bool,pct:int} */
    public static function progress(Site $site): array
    {
        $steps = self::steps($site);
        $done = count(array_filter($steps, fn ($s) => $s['done']));
        $total = count($steps);

        return ['done' => $done, 'total' => $total, 'complete' => $done === $total, 'pct' => $total ? (int) round($done / $total * 100) : 100];
    }

    public static function complete(Site $site): bool
    {
        return self::progress($site)['complete'];
    }
}
