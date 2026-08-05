<?php

namespace Database\Seeders;

use App\Models\SiteActivityLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class SiteActivitySeeder extends Seeder
{
    public function run(): void
    {
        $siteId = 4;
        $user   = User::first();
        $uid    = $user->id;

        $activities = [
            [
                'entity_type' => 'page',
                'entity_id'   => 1,
                'action'      => 'published',
                'title'       => 'Page "Home" was published',
                'description' => 'The homepage is now live and visible to visitors.',
                'url'         => '/pages',
                'icon'        => 'page',
                'meta'        => ['page_url' => '/'],
                'created_at'  => now()->subMinutes(5),
            ],
            [
                'entity_type' => 'form_response',
                'entity_id'   => 1,
                'action'      => 'responded',
                'title'       => 'New response on "Contact Us"',
                'description' => 'Jane Doe submitted the contact form.',
                'url'         => '/forms',
                'icon'        => 'response',
                'meta'        => ['form_name' => 'Contact Us', 'email' => 'jane.doe@example.com'],
                'created_at'  => now()->subMinutes(18),
            ],
            [
                'entity_type' => 'todo',
                'entity_id'   => 1,
                'action'      => 'created',
                'title'       => 'Task "Redesign pricing section" was added',
                'description' => 'Assigned to the design team for this sprint.',
                'url'         => '/todos',
                'icon'        => 'todo',
                'meta'        => ['priority' => 'high', 'status' => 'todo'],
                'created_at'  => now()->subMinutes(45),
            ],
            [
                'entity_type' => 'media',
                'entity_id'   => 1,
                'action'      => 'uploaded',
                'title'       => 'Media "hero-banner.png" was uploaded',
                'description' => 'image/png · 1.4 MB',
                'url'         => '/media',
                'icon'        => 'media',
                'meta'        => ['mime_type' => 'image/png', 'size' => 1468006],
                'created_at'  => now()->subHours(1),
            ],
            [
                'entity_type' => 'form',
                'entity_id'   => 1,
                'action'      => 'created',
                'title'       => 'Form "Newsletter Signup" was created',
                'description' => 'Collects name and email from visitors.',
                'url'         => '/forms',
                'icon'        => 'form',
                'meta'        => ['form_name' => 'Newsletter Signup'],
                'created_at'  => now()->subHours(2),
            ],
            [
                'entity_type' => 'member',
                'entity_id'   => 2,
                'action'      => 'joined',
                'title'       => 'Alex Morgan joined the team',
                'description' => 'alex.morgan@nexonix.io',
                'url'         => '/team',
                'icon'        => 'member',
                'meta'        => ['user_email' => 'alex.morgan@nexonix.io'],
                'created_at'  => now()->subHours(3),
            ],
            [
                'entity_type' => 'page',
                'entity_id'   => 2,
                'action'      => 'created',
                'title'       => 'Page "Pricing" was created',
                'description' => 'URL: /pricing',
                'url'         => '/pages',
                'icon'        => 'page',
                'meta'        => ['page_url' => '/pricing', 'is_published' => false],
                'created_at'  => now()->subHours(5),
            ],
            [
                'entity_type' => 'todo',
                'entity_id'   => 2,
                'action'      => 'completed',
                'title'       => 'Task "Write blog post draft" was completed',
                'description' => null,
                'url'         => '/todos',
                'icon'        => 'todo',
                'meta'        => ['priority' => 'normal'],
                'created_at'  => now()->subHours(8),
            ],
            [
                'entity_type' => 'form_response',
                'entity_id'   => 2,
                'action'      => 'responded',
                'title'       => 'New response on "Newsletter Signup"',
                'description' => 'Carlos Rivera signed up for the newsletter.',
                'url'         => '/forms',
                'icon'        => 'response',
                'meta'        => ['form_name' => 'Newsletter Signup', 'email' => 'c.rivera@email.com'],
                'created_at'  => now()->subHours(12),
            ],
        ];

        foreach ($activities as $data) {
            SiteActivityLog::create(array_merge($data, [
                'site_id'    => $siteId,
                'user_id'    => $uid,
                'updated_at' => $data['created_at'],
            ]));
        }

        $this->command->info('Seeded ' . count($activities) . ' activities for site ' . $siteId . '.');
    }
}
