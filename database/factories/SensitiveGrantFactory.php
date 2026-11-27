<?php

namespace Database\Factories;

use App\Enums\SensitiveField;
use App\Models\Person;
use App\Models\SensitiveGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SensitiveGrant>
 */
class SensitiveGrantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'field' => SensitiveField::DealValues,
            'entity_id' => fn () => EntityFactory::defaultId(),
        ];
    }
}
