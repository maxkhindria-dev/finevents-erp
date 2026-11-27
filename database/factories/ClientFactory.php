<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_id' => fn () => EntityFactory::defaultId(),
            'name' => fake()->company().' (test)',
            'sector' => fake()->randomElement(['Bank', 'Asset manager', 'Insurer', 'Private equity']),
        ];
    }
}
