<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        $words = fake()->unique()->words(2);
        // The suffix keeps names unique ACROSS test runs too — the testing DB
        // persists between runs, and name-resolving endpoints (site lookup by
        // name) go flaky when a fresh site collides with a leftover one.
        $words[] = strtolower(Str::random(6));

        return [
            'name' => implode('-', $words),
            'domain' => implode('', $words).'.com',
            'owner' => fake()->name(),
            'description' => fake()->sentence(),
        ];
    }
}
