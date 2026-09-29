<?php

namespace App\Support;

use App\Models\ContentVersion;
use App\Models\DomainOrder;
use App\Models\Site;
use App\Models\SiteTemplate;
use App\Models\Todo;

/**
 * The "Set up your site" task every site gets: a real task (Tasks page) whose
 * items are the steps below. STEPS is the single definition — add a step here
 * and every site's task gains it on its next sync, and the dashboard checklist
 * (which renders from the task) shows it. Items tick themselves when the real
 * data says the step is done; people can also tick them by hand.
 */
class SiteSetupTask
{
    public const KEY = 'setup';

    /** Keys already added to the site's task — items people delete aren't re-added. */
    private const SEEDED_ATTR = 'setup_task.seeded';

    /** @var array<string, array{label:string, description:string, path:string, cta:string}> */
    public const STEPS = [
        'properties' => [
            'label' => 'Update site properties',
            'description' => 'Add your site name, logo, email and phone numbers.',
            'path' => 'properties',
            'cta' => 'Open properties',
        ],
        'choose_template' => [
            'label' => 'Choose a template',
            'description' => 'Pick the design your site uses — you can change it later without losing content.',
            'path' => 'marketplace',
            'cta' => 'Browse templates',
        ],
        'update_content' => [
            'label' => 'Update site content',
            'description' => 'Open edit mode and put your own words and pictures on each page.',
            'path' => 'connect',
            'cta' => 'Edit site',
        ],
        'get_domain' => [
            'label' => 'Get a domain name',
            'description' => 'Buy a new web address, or connect one you already own.',
            'path' => 'publish',
            'cta' => 'Get a domain',
        ],
        'go_live' => [
            'label' => 'Get site live',
            'description' => 'Switch the site on so visitors can find it.',
            'path' => 'publish',
            'cta' => 'Go live',
        ],
    ];

    public static function url(Site $site, string $key): ?string
    {
        return isset(self::STEPS[$key]) ? url($site->name.'/'.self::STEPS[$key]['path']) : null;
    }

    /** @return array<string,bool> step key => done according to the site's real data */
    public static function detect(Site $site): array
    {
        return [
            'properties' => filled($site->getAttr(SiteProperties::NAME)),
            'choose_template' => (filled($site->template) && $site->template !== 'blank')
                || SiteTemplate::where('site_id', $site->id)->whereNotNull('applied_at')->exists(),
            'update_content' => ContentVersion::where('site_id', $site->id)->exists(),
            'get_domain' => $site->domain_verified_at !== null
                || DomainOrder::where('site_id', $site->id)->where('status', 'registered')->exists(),
            'go_live' => (bool) $site->live,
        ];
    }

    /**
     * Create the task if the site has none, add any steps it hasn't had yet,
     * keep step wording current and tick steps the data shows are done.
     * Returns null when the owner deleted the task (it isn't recreated).
     */
    public static function sync(Site $site): ?Todo
    {
        $todo = Todo::where('site_id', $site->id)->where('system_key', self::KEY)->with('items')->first();
        $seeded = json_decode((string) $site->getAttr(self::SEEDED_ATTR), true);

        if (! $todo) {
            if (is_array($seeded)) {
                return null;
            }
            $todo = Todo::create([
                'site_id' => $site->id,
                'system_key' => self::KEY,
                'user_id' => $site->user_id,
                'assigned_user_id' => $site->user_id,
                'title' => 'Set up your site',
                'description' => 'The steps to get this site ready for visitors. Each one ticks itself off when it\'s done.',
                'status' => 'open',
                'priority' => 'high',
                'sort' => 0,
            ]);
            $todo->setRelation('items', collect());
            $seeded = [];
        }
        $seeded = is_array($seeded) ? $seeded : $todo->items->pluck('key')->filter()->values()->all();

        $detected = self::detect($site);
        $added = false;
        $byKey = $todo->items->keyBy('key');
        $position = 0;

        foreach (self::STEPS as $key => $step) {
            $position++;
            $item = $byKey->get($key);
            if (! $item) {
                if (in_array($key, $seeded, true)) {
                    continue; // removed by a person — leave it out
                }
                $todo->items()->create([
                    'key' => $key,
                    'label' => $step['label'],
                    'description' => $step['description'],
                    'done' => $detected[$key] ?? false,
                    'sort' => $position,
                ]);
                $seeded[] = $key;
                $added = true;

                continue;
            }

            $changes = [];
            if ($item->label !== $step['label'] || $item->description !== $step['description']) {
                $changes += ['label' => $step['label'], 'description' => $step['description']];
            }
            if (! $item->done && ($detected[$key] ?? false)) {
                $changes['done'] = true;
            }
            if ($changes) {
                $item->update($changes);
            }
        }

        if ($added || $site->getAttr(self::SEEDED_ATTR) === null) {
            $site->setAttr(self::SEEDED_ATTR, json_encode(array_values(array_unique($seeded))));
        }

        $todo->load('items');
        $allDone = $todo->items->isNotEmpty() && $todo->items->every(fn ($i) => $i->done);
        if ($allDone && $todo->status !== 'done') {
            $todo->update(['status' => 'done', 'completed_at' => now()]);
        } elseif (! $allDone && $todo->status === 'done' && $added) {
            $todo->update(['status' => 'open', 'completed_at' => null]); // a new step reopens it
        }

        return $todo;
    }

    /**
     * Dashboard rows: the task's items in order, each linking to its page.
     *
     * @return list<array{key:?string,label:string,description:?string,done:bool,url:?string,cta:?string}>
     */
    public static function rows(Site $site, Todo $todo): array
    {
        return $todo->items->map(fn ($i) => [
            'key' => $i->key,
            'label' => $i->label,
            'description' => $i->description,
            'done' => (bool) $i->done,
            'url' => $i->key ? self::url($site, $i->key) : null,
            'cta' => self::STEPS[$i->key]['cta'] ?? null,
        ])->values()->all();
    }
}
