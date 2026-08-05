<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class PageFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'site_id'  => Site::factory(),
            'name'     => ucwords($name),
            'url'      => '/' . str_replace(' ', '-', strtolower($name)),
            'keywords' => implode(', ', fake()->words(5)),
        ];
    }
}
