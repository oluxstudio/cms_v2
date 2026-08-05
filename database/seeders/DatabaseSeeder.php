<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Site;
use App\Models\Page;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Test user
        User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        // 5 sites, each with 5 pages, 5 components, and 5 nodes per component
        Site::factory(5)->create()->each(function (Site $site) {
            Page::factory(5)->create(['site_id' => $site->id]);

        });
    }
}
