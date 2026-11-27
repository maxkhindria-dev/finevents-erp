<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Entity;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'code' => 'FR-2026-'.str_pad((string) (++self::$sequence % 1000), 3, '0', STR_PAD_LEFT),
            'client_id' => Client::factory(),
            // Same entity as the client, so generated data is always consistent.
            'entity_id' => fn (array $attributes) => (int) DB::table('clients')->where('id', $attributes['client_id'])->value('entity_id'),
            'name' => fake()->randomElement(['Investor Forum', 'Annual Summit', 'Roundtable', 'Client Dinner']).' '.fake()->year(),
            'starts_on' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'venue' => fake()->city(),
            'status' => ProjectStatus::Planned,
        ];
    }

    /** Project in a given entity, for a client of that entity, with a matching code. */
    public function in(Entity $entity, ?Client $client = null): static
    {
        return $this->state(fn () => [
            'entity_id' => $entity->id,
            'client_id' => $client->id ?? Client::factory()->state(['entity_id' => $entity->id]),
            'code' => $entity->code.'-2026-'.str_pad((string) (++self::$sequence % 1000), 3, '0', STR_PAD_LEFT),
        ]);
    }
}
