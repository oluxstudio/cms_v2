<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComponentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id'     => Site::factory(),
            'name'        => ucwords(fake()->words(2, true)) . ' Component',
            'author'      => fake()->name(),
            'description' => fake()->sentence(),
        ];
    }
}
