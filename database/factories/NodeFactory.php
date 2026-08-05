<?php

namespace Database\Factories;

use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;

class NodeFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['text', 'number', 'boolean', 'color', 'url']);

        return [
            'component_id' => Component::factory(),
            'label'        => ucfirst(fake()->word()),
            'value'        => match ($type) {
                'number'  => (string) fake()->numberBetween(1, 999),
                'boolean' => fake()->randomElement(['true', 'false']),
                'color'   => fake()->hexColor(),
                'url'     => fake()->url(),
                default   => fake()->words(2, true),
            },
            'type'         => $type,
            'order'        => fake()->numberBetween(1, 20),
            'description'  => fake()->sentence(),
        ];
    }
}
