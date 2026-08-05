<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        $words = fake()->unique()->words(2);

        return [
            'name'        => implode('-', $words),
            'domain'      => implode('', $words) . '.com',
            'owner'       => fake()->name(),
            'description' => fake()->sentence(),
        ];
    }
}
