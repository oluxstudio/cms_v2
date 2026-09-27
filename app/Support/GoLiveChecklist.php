<?php

namespace App\Support;

use App\Models\DomainOrder;
use App\Models\Site;

/**
 * The client-facing stages to going live: Site ready → Web address →
 * Connected → Live. Pure reads — the Go-live page renders these as a
 * stepper. Captions always reflect the tenant's REAL state (the actual
 * domain once saved, "Buying" while a purchase is in flight).
 */
class GoLiveChecklist
{
    /**
     * @return list<array{key:string,label:string,description:string,state:string,tab:?string}>
     *                                                                                          state: done | working | active | todo
     */
    public static function steps(Site $site): array
    {
        $order = DomainOrder::where('site_id', $site->id)
            ->whereIn('status', ['paid', 'pending'])
            ->where('type', 'register')->latest()->first();
        $buying = $order !== null && ! filled($site->domain);

        $steps = [
            [
                'key' => 'template',
                'label' => 'Site ready',
                'description' => (filled($site->template) || $site->pages()->exists())
                    ? 'Template chosen'
                    : 'Pick a template in the Marketplace first',
                'done' => filled($site->template) || $site->pages()->exists(),
                'tab' => null,
            ],
            [
                'key' => 'domain',
                'label' => 'Web address',
                'description' => filled($site->domain)
                    ? $site->domain
                    : ($buying ? 'Buying '.$order->domain.'…' : 'Pick one option below'),
                'done' => filled($site->domain),
                'working' => $buying,
                'tab' => 'domain',
            ],
            [
                'key' => 'dns',
                'label' => 'Connected',
                'description' => $site->domain_verified_at
                    ? (DomainOrder::where('site_id', $site->id)->where('domain', $site->domain)->where('status', 'registered')->exists()
                        ? 'Automatic' : 'Verified '.$site->domain_verified_at->diffForHumans())
                    : 'We check everything for you',
                'done' => $site->domain_verified_at !== null,
                'tab' => 'connect',
            ],
            [
                'key' => 'live',
                'label' => 'Live',
                'description' => $site->live ? 'Serving visitors' : 'Unlocks after the checks',
                'done' => (bool) $site->live,
                'tab' => 'live',
            ],
        ];

        // First not-done step is ACTIVE ("You are here"); the rest stay todo.
        $activeSeen = false;

        return array_map(function (array $s) use (&$activeSeen) {
            $state = match (true) {
                $s['done'] => 'done',
                $s['working'] ?? false => 'working',
                ! $activeSeen => 'active',
                default => 'todo',
            };
            if ($state === 'active' || $state === 'working') {
                $activeSeen = true;
            }
            if ($state === 'active') {
                $s['description'] = $s['key'] === 'domain' && $s['description'] === 'Pick one option below'
                    ? 'You are here' : $s['description'];
            }

            return ['key' => $s['key'], 'label' => $s['label'], 'description' => $s['description'], 'state' => $state, 'tab' => $s['tab']];
        }, $steps);
    }

    /** @return array{done:int,total:int,pct:int,complete:bool} */
    public static function progress(Site $site): array
    {
        $steps = self::steps($site);
        $done = count(array_filter($steps, fn ($s) => $s['state'] === 'done'));
        $total = count($steps);

        return ['done' => $done, 'total' => $total, 'pct' => (int) round($done / $total * 100), 'complete' => $done === $total];
    }
}
