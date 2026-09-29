<?php

namespace App\Support;

use App\Models\ContentVersion;
use App\Models\DomainOrder;
use App\Models\SiteTemplate;
use App\Models\User;

/**
 * The get-started checklist: the five steps from sign-up to a live site,
 * each DETECTED from the account's real data (no manual ticking). One
 * definition, used by the first-login intro pack and the getting started guide.
 */
class Onboarding
{
    /**
     * @return list<array{key:string,label:string,description:string,done:bool,cta_url:?string,cta_label:string}>
     */
    public static function steps(User $user): array
    {
        $sites = $user->sites()->latest('id')->get();
        $siteIds = $sites->pluck('id');
        $site = $sites->first();
        $to = fn (string $path) => $site ? url($site->name.'/'.$path) : null;
        $hasSite = $siteIds->isNotEmpty();

        $hasTemplate = $hasSite && (
            SiteTemplate::whereIn('site_id', $siteIds)->whereNotNull('applied_at')->exists()
            || $sites->contains(fn ($s) => filled($s->template) && $s->template !== 'blank')
        );
        // Any save in edit mode leaves a content version behind.
        $hasEdits = $hasSite && ContentVersion::whereIn('site_id', $siteIds)->exists();
        $hasDomain = $hasSite && (
            $sites->contains(fn ($s) => $s->domain_verified_at !== null)
            || DomainOrder::where('user_id', $user->id)->where('status', 'registered')->exists()
        );
        $isLive = $sites->contains(fn ($s) => (bool) $s->live);

        return [
            [
                'key' => 'create_site',
                'label' => 'Create your site',
                'description' => 'Give it a name — it\'s the home for your pages, content and customers.',
                'done' => $hasSite,
                'cta_url' => null,               // handled by the "New site" button
                'cta_label' => 'Create site',
            ],
            [
                'key' => 'choose_template',
                'label' => 'Choose a template',
                'description' => 'Pick the design your site uses. You can change it later without losing content.',
                'done' => $hasTemplate,
                'cta_url' => $to('design'),
                'cta_label' => 'Choose template',
            ],
            [
                'key' => 'update_content',
                'label' => 'Update your pages',
                'description' => 'Open edit mode, click any section and put in your own words and pictures.',
                'done' => $hasEdits,
                'cta_url' => $to('connect'),
                'cta_label' => 'Edit pages',
            ],
            [
                'key' => 'get_domain',
                'label' => 'Get a domain name',
                'description' => 'Buy a new web address, or connect one you already own.',
                'done' => $hasDomain,
                'cta_url' => $to('publish'),
                'cta_label' => 'Get a domain',
            ],
            [
                'key' => 'go_live',
                'label' => 'Put your site live',
                'description' => 'Switch it on so visitors can find it on your address.',
                'done' => $isLive,
                'cta_url' => $to('publish'),
                'cta_label' => 'Go live',
            ],
        ];
    }

    /** @return array{done:int,total:int,complete:bool} */
    public static function progress(User $user): array
    {
        $steps = self::steps($user);
        $done = count(array_filter($steps, fn ($s) => $s['done']));
        $total = count($steps);

        return ['done' => $done, 'total' => $total, 'complete' => $done === $total];
    }
}
