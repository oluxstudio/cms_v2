<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Six realistic demo tasks (items, dates, assignees, comments) on one site.
 *
 *   DEMO_SITE=kimo php artisan db:seed --class=DemoTasksSeeder
 *
 * Adds two demo teammates to the site if it has fewer than three members, so
 * assignments and avatar rows have someone to show.
 */
class DemoTasksSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('name', env('DEMO_SITE', 'kimo'))->firstOrFail();
        $owner = $site->user;

        $team = $site->members()->get();
        if ($team->count() < 3) {
            foreach ([['Maya Okafor', 'maya.demo'], ['Tom Reilly', 'tom.demo']] as [$name, $slug]) {
                $u = User::firstOrCreate(['email' => "{$slug}@{$site->name}.example"], [
                    'name' => $name, 'password' => Hash::make(str()->random(24)), 'email_verified_at' => now(),
                ]);
                $site->members()->syncWithoutDetaching([$u->id => ['role' => 'editor']]);
            }
            $team = $site->members()->get();
        }
        $pick = fn (int $i) => $team[$i % $team->count()];

        $d = fn (int $days) => now()->addDays($days);
        $tasks = [
            [
                'title' => 'Launch the new website', 'priority' => 'high', 'status' => 'in_progress',
                'starts_at' => $d(-6)->startOfDay(), 'due_at' => $d(8)->endOfDay(), 'assignee' => 0,
                'description' => 'Get the site live on the new domain with all pages, booking and payments working.',
                'items' => [
                    ['Write homepage copy', true, 1, -6, -3, 'Hero, services blurb and about section.'],
                    ['Upload gallery photos', true, 2, -4, -1, '12 before/after shots, compressed.'],
                    ['Connect Stripe for deposits', false, 0, -1, 3, 'Test a £10 deposit end to end.'],
                    ['Point domain and verify DNS', false, 0, 4, 6, null],
                    ['Final walkthrough on mobile', false, 1, 7, 8, null],
                ],
                'comments' => [[1, 'Copy is in — flagging two lines for you to check.'], [0, 'Looks great, tweaked the hero line.']],
            ],
            [
                'title' => 'Set up online bookings', 'priority' => 'high', 'status' => 'open',
                'starts_at' => $d(1)->startOfDay(), 'due_at' => $d(5)->endOfDay(), 'assignee' => 1,
                'description' => 'Services, staff and opening hours so clients can book without calling.',
                'items' => [
                    ['Add all services with prices', false, 1, 1, 2, null],
                    ['Add staff and their hours', false, 1, 2, 3, null],
                    ['Set deposit rules', false, 0, 3, 4, '20% deposit on colour, none on cuts.'],
                    ['Test a booking as a client', false, 2, 5, 5, null],
                ],
                'comments' => [],
            ],
            [
                'title' => 'Send September newsletter', 'priority' => 'normal', 'status' => 'open',
                'starts_at' => null, 'due_at' => $d(-2)->endOfDay(), 'assignee' => 2,
                'description' => 'Autumn offers + new stylist intro.',
                'items' => [
                    ['Draft the email', true, 2, null, null, null],
                    ['Pick a header image', false, 1, null, null, null],
                    ['Schedule send for Tuesday 9am', false, 2, null, null, null],
                ],
                'comments' => [[2, 'Draft is ready for a read-through.']],
            ],
            [
                'title' => 'Chase overdue invoices', 'priority' => 'high', 'status' => 'open',
                'starts_at' => null, 'due_at' => null, 'assignee' => 0,
                'description' => 'Three invoices over 14 days. Friendly reminder first, then a call.',
                'items' => [
                    ['INV-014 — reminder email', false, 0, null, null, null],
                    ['INV-016 — call', false, 0, null, null, null],
                    ['INV-019 — reminder email', false, 0, null, null, null],
                ],
                'comments' => [],
            ],
            [
                'title' => 'Photograph the salon', 'priority' => 'low', 'status' => 'done',
                'starts_at' => $d(-14)->startOfDay(), 'due_at' => $d(-10)->endOfDay(), 'assignee' => 1,
                'description' => 'Fresh photos for the website and Instagram.',
                'items' => [
                    ['Front of shop', true, 1, -14, -13, null],
                    ['Interior and chairs', true, 1, -13, -12, null],
                    ['Team photo', true, 1, -11, -10, 'Everyone in black tops.'],
                ],
                'comments' => [[1, 'All done — uploaded to Assets.'], [0, 'Perfect, thank you!']],
            ],
            [
                'title' => 'Review the colour theme', 'priority' => 'normal', 'status' => 'in_progress',
                'starts_at' => $d(-2)->startOfDay(), 'due_at' => $d(12)->endOfDay(), 'assignee' => 2,
                'description' => 'Match the site colours to the new branding — warm neutrals with a sage accent.',
                'items' => [
                    ['Pick primary and accent colours', true, 2, -2, 0, null],
                    ['Update logo files', false, 2, 1, 4, 'SVG + PNG at 2x.'],
                    ['Check contrast on buttons', false, 0, 5, 6, null],
                    ['Apply to email templates', false, 1, 8, 12, null],
                ],
                'comments' => [[2, 'Going with #7C8B6F for the accent — thoughts?']],
            ],
        ];

        foreach ($tasks as $t) {
            $task = $site->todos()->create([
                'user_id' => $owner->id, 'assigned_user_id' => $pick($t['assignee'])->id,
                'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'], 'status' => $t['status'],
                'starts_at' => $t['starts_at'], 'due_at' => $t['due_at'],
                'completed_at' => $t['status'] === 'done' ? $d(-10) : null,
            ]);
            foreach ($t['items'] as $i => [$label, $done, $who, $from, $to, $desc]) {
                $task->items()->create([
                    'label' => $label, 'done' => $done, 'sort' => $i + 1, 'description' => $desc,
                    'assigned_user_id' => $pick($who)->id,
                    'starts_at' => $from !== null ? $d($from)->startOfDay() : null,
                    'ends_at' => $to !== null ? $d($to)->endOfDay() : null,
                ]);
            }
            foreach ($t['comments'] as $j => [$who, $body]) {
                $task->comments()->create(['user_id' => $pick($who)->id, 'body' => $body, 'created_at' => now()->subHours(30 - $j * 5)]);
            }
            $task->timestamps = false;
            $task->update(['created_at' => $t['starts_at'] ?? now()->subDays(3)]);
        }

        $this->command?->info("Seeded 6 demo tasks on {$site->name} with team: ".$team->pluck('name')->implode(', '));
    }
}
