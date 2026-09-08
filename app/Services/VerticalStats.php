<?php

namespace App\Services;

use App\Models\Site;
use App\Services\Blueprints\BlueprintRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The vertical widget pack on the Run dashboard: the numbers a salon owner or
 * a tradesperson actually checks in the morning, chosen by the site's
 * business_type. Cheap site-scoped aggregate queries, empty-state safe.
 */
class VerticalStats
{
    /** @return array{pack:string,tiles:list<array>,list_title:string,list:list<array>,histogram:?array}|null */
    public static function for(Site $site): ?array
    {
        $type = (string) $site->getAttr('business_type');
        $pack = BlueprintRegistry::types()[$type]['blueprint'] ?? null;

        return match ($pack) {
            'salon' => self::salon($site),
            'trades' => self::trades($site),
            default => null,
        };
    }

    private static function salon(Site $site): array
    {
        $weeks = (int) (($site->feature('bookings')['rebook_weeks'] ?? 5) ?: 5);
        $b = fn () => DB::table('bookings')->where('site_id', $site->id);

        // Clients due back: last visit older than the rebook window (but not
        // ancient), with no later booking on the books.
        $dueBack = $b()->selectRaw('LOWER(customer_email) as email, MAX(customer_name) as name, MAX(starts_at) as last_at')
            ->whereNotIn('status', ['cancelled'])
            ->groupBy('email')
            ->havingRaw('MAX(starts_at) < ?', [now()->subWeeks($weeks)])
            ->havingRaw('MAX(starts_at) > ?', [now()->subMonths(6)])
            ->orderByDesc('last_at')->limit(5)->get();

        // No-shows + late cancellations this week. Late-cancel is a heuristic:
        // cancelled within 24h of the start time (cancellation time isn't stored).
        $week = $b()->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->selectRaw("SUM(status = 'no_show') as no_shows")
            ->selectRaw("SUM(status = 'cancelled' AND updated_at >= starts_at - INTERVAL 24 HOUR) as late_cancels")
            ->first();

        // Busiest hours, last 7 days.
        $hours = $b()->whereNotIn('status', ['cancelled'])
            ->where('starts_at', '>=', now()->subDays(7))
            ->selectRaw('HOUR(starts_at) as h, COUNT(*) as c')
            ->groupBy('h')->pluck('c', 'h');
        $histogram = collect(range(8, 20))->map(fn ($h) => ['hour' => $h, 'count' => (int) ($hours[$h] ?? 0)])->all();
        $hasHours = collect($histogram)->sum('count') > 0;

        // Rebooking rate: distinct clients with more than one visit, 90 days.
        $rate = $b()->whereNotIn('status', ['cancelled'])
            ->where('starts_at', '>=', now()->subDays(90))
            ->selectRaw('LOWER(customer_email) as email, COUNT(*) as cnt')
            ->groupBy('email')->get();
        $repeat = $rate->count() ? (int) round($rate->where('cnt', '>', 1)->count() / $rate->count() * 100) : null;

        return [
            'pack' => 'salon',
            'tiles' => [
                ['label' => 'Rebooking rate', 'value' => $repeat === null ? '—' : $repeat.'%', 'hint' => 'clients with a repeat visit, 90 days'],
                ['label' => 'No-shows', 'value' => (int) ($week->no_shows ?? 0), 'hint' => 'this week', 'flag' => (int) ($week->no_shows ?? 0) > 0],
                ['label' => 'Late cancels', 'value' => (int) ($week->late_cancels ?? 0), 'hint' => 'within 24h (approx.)'],
            ],
            'list_title' => 'Clients due back',
            'list' => $dueBack->map(fn ($r) => [
                'title' => $r->name ?: $r->email,
                'sub' => 'last visit '.Carbon::parse($r->last_at)->diffForHumans(),
                'href' => '/'.$site->name.'/contacts',
            ])->all(),
            'histogram' => $hasHours ? $histogram : null,
        ];
    }

    private static function trades(Site $site): array
    {
        $pipeline = DB::table('estimates')->where('site_id', $site->id)
            ->selectRaw('status, COUNT(*) as cnt, SUM(cost_low_cents) as low, SUM(cost_high_cents) as high')
            ->groupBy('status')->get()->keyBy('status');
        $oldestNew = DB::table('estimates')->where('site_id', $site->id)->where('status', 'new')->min('created_at');
        $chaseDays = $oldestNew ? (int) Carbon::parse($oldestNew)->diffInDays(now()) : null;

        // Lead response speed: contact created → first activity by a human.
        $rows = DB::table('contacts')->where('contacts.site_id', $site->id)
            ->where('contacts.created_at', '>=', now()->subDays(30))
            ->joinSub(
                DB::table('activities')->whereNotNull('user_id')->selectRaw('contact_id, MIN(created_at) as first_reply')->groupBy('contact_id'),
                'r', 'r.contact_id', '=', 'contacts.id'
            )
            ->selectRaw('TIMESTAMPDIFF(HOUR, contacts.created_at, r.first_reply) as hours')
            ->groupBy('contacts.id', 'contacts.created_at', 'r.first_reply')
            ->pluck('hours')->map(fn ($h) => max(0, (int) $h))->sort()->values();
        $median = $rows->count() ? $rows[intdiv($rows->count() - 1, 2)] : null;

        $jobsThisWeek = DB::table('bookings')->where('site_id', $site->id)->where('status', 'confirmed')
            ->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count();

        $cnt = fn ($s) => (int) ($pipeline[$s]->cnt ?? 0);
        $fmt = fn ($cents) => '£'.number_format(((int) $cents) / 100, 0);

        return [
            'pack' => 'trades',
            'tiles' => [
                ['label' => 'New leads', 'value' => $cnt('new'),
                    'hint' => $chaseDays !== null && $chaseDays > 7 ? "oldest waiting {$chaseDays} days — chase!" : 'awaiting first contact',
                    'flag' => $chaseDays !== null && $chaseDays > 7],
                ['label' => 'Quotes out', 'value' => $cnt('contacted'), 'hint' => $fmt($pipeline['contacted']->low ?? 0).'–'.$fmt($pipeline['contacted']->high ?? 0).' on the table'],
                ['label' => 'Won', 'value' => $cnt('won'), 'hint' => ($cnt('won') + $cnt('lost')) > 0 ? round($cnt('won') / ($cnt('won') + $cnt('lost')) * 100).'% win rate' : 'no outcomes yet'],
            ],
            'list_title' => 'Pipeline',
            'list' => array_values(array_filter([
                ['title' => 'Jobs this week', 'sub' => $jobsThisWeek.' confirmed', 'href' => '/'.$site->name.'/bookings'],
                $median !== null ? ['title' => 'Lead response speed', 'sub' => "median {$median}h to first reply (30 days)", 'href' => '/'.$site->name.'/contacts'] : null,
                ['title' => 'Quotes new / sent / won / lost', 'sub' => $cnt('new').' / '.$cnt('contacted').' / '.$cnt('won').' / '.$cnt('lost'), 'href' => '/'.$site->name.'/estimates'],
            ])),
            'histogram' => null,
        ];
    }
}
