<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\Component;
use App\Models\Form;
use App\Models\User;

/**
 * The onboarding checklist: value-focused steps whose completion is DETECTED
 * from the user's real data (no manual ticking). One definition, reused by the
 * checklist widget.
 */
class Onboarding
{
    /**
     * @return list<array{key:string,label:string,description:string,done:bool,cta_url:?string,cta_label:string}>
     */
    public static function steps(User $user): array
    {
        $siteIds = $user->sites()->pluck('id');
        $site = $user->sites()->latest('id')->first();
        $to = fn (string $path) => $site ? url($site->name.'/'.$path) : null;

        $hasSite = $siteIds->isNotEmpty();

        $hasContent = $hasSite && (
            Component::whereIn('site_id', $siteIds)->exists()
            || Collection::whereIn('site_id', $siteIds)->whereHas('components')->exists()
        );

        $hasForm = $hasSite && Form::whereIn('site_id', $siteIds)->exists();

        $hasBranding = $hasSite && $user->sites()->get()
            ->contains(fn ($s) => filled($s->getAttr('email.logo')) || filled($s->theme));

        $hasTeam = $hasSite && $user->sites()->get()
            ->contains(fn ($s) => $s->members()->count() > 1);

        return [
            [
                'key' => 'create_site',
                'label' => 'Create your first site',
                'description' => 'Spin up a site — the home for your pages, content and leads.',
                'done' => $hasSite,
                'cta_url' => null,               // handled by the "New site" button
                'cta_label' => 'Create site',
            ],
            [
                'key' => 'add_content',
                'label' => 'Add your content',
                'description' => 'Build a component or a collection so your pages have something to show.',
                'done' => $hasContent,
                'cta_url' => $to('components'),
                'cta_label' => 'Add content',
            ],
            [
                'key' => 'capture_leads',
                'label' => 'Capture leads',
                'description' => 'Add a form — submissions land straight in your CRM.',
                'done' => $hasForm,
                'cta_url' => $to('forms'),
                'cta_label' => 'Add a form',
            ],
            [
                'key' => 'branding',
                'label' => 'Make it yours',
                'description' => 'Add your logo and colours so it feels like your brand.',
                'done' => $hasBranding,
                'cta_url' => $to('emails'),
                'cta_label' => 'Add branding',
            ],
            [
                'key' => 'invite',
                'label' => 'Invite a teammate',
                'description' => 'Bring the team in — assign roles and share the workload.',
                'done' => $hasTeam,
                'cta_url' => $to('team'),
                'cta_label' => 'Invite',
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
