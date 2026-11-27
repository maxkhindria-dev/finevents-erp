<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_id' => fn () => EntityFactory::defaultId(),
            'name' => fake()->unique()->randomElement(['Events', 'Production', 'Sales', 'Finance', 'Operations']).' '.fake()->unique()->numberBetween(1, 999),
        ];
    }
}
