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
    public static function steps(Site $site, ?string $flow = null, ?string $buyStage = null, ?string $buyDomain = null): array
    {
        $order = DomainOrder::where('site_id', $site->id)
            ->where('status', 'paid')   // paid, being registered (unpaid = `checkout`)
            ->where('type', 'register')->latest()->first();
        $buying = $order !== null && ! filled($site->domain);

        // Buying through us: Site ready → Web address → Payment → Live
        // (connecting is automatic, so the payment takes its place).
        $bought = DomainOrder::where('site_id', $site->id)->where('type', 'register')
            ->where('status', 'registered')->where('domain', $site->domain)->exists();
        if (! $site->live && ($flow === 'buy' || $buying)) {
            $chosen = $buying ? $order->domain : (in_array($buyStage, ['chosen', 'pay'], true) ? $buyDomain : null);
            $steps = [
                self::readyStep($site),
                [
                    'key' => 'domain',
                    'label' => 'Web address',
                    'description' => $chosen ?: 'Pick one option below',
                    'done' => filled($chosen),
                    'tab' => 'domain',
                ],
                [
                    'key' => 'payment',
                    'label' => 'Payment',
                    'description' => $buying ? 'Paid · registering '.$order->domain.'…' : ($buyStage === 'pay' ? 'Pay securely below' : 'Card, Apple Pay or Google Pay'),
                    'done' => false,
                    'working' => $buying,
                    'tab' => 'payment',
                ],
                [
                    'key' => 'live',
                    'label' => 'Live',
                    'description' => 'Automatic once paid',
                    'done' => false,
                    'tab' => 'live',
                ],
            ];

            return self::withStates($steps);
        }

        $steps = [
            self::readyStep($site),
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
                    ? ($bought ? 'Automatic' : 'Verified '.$site->domain_verified_at->diffForHumans())
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

        return self::withStates($steps);
    }

    private static function readyStep(Site $site): array
    {
        $ready = filled($site->template) || $site->pages()->exists();

        return [
            'key' => 'template',
            'label' => 'Site ready',
            'description' => $ready ? 'Template chosen' : 'Pick a template in the Marketplace first',
            'done' => $ready,
            'tab' => null,
        ];
    }

    /** First not-done step is ACTIVE ("You are here"); the rest stay todo. */
    private static function withStates(array $steps): array
    {
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
