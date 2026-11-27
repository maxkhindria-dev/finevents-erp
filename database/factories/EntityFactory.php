<?php

namespace Database\Factories;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    /** The three real FinEvents entities (company structure, not personal data). */
    public const ENTITIES = [
        'FR' => ['name' => 'FinEvents France', 'country' => 'FR', 'currency' => 'EUR', 'accounting_system' => 'pennylane'],
        'UK' => ['name' => 'FinEvents UK', 'country' => 'GB', 'currency' => 'GBP', 'accounting_system' => 'xero'],
        'US' => ['name' => 'FinEvents USA', 'country' => 'US', 'currency' => 'USD', 'accounting_system' => 'xero'],
    ];

    public function definition(): array
    {
        return ['code' => 'FR'] + self::ENTITIES['FR'];
    }

    /**
     * ID of an existing entity (France by default), created if missing.
     * Lets other factories point at an entity without creating duplicates.
     */
    public static function defaultId(string $code = 'FR'): int
    {
        return (int) Entity::query()->withoutGlobalScopes()
            ->firstOrCreate(['code' => $code], self::ENTITIES[$code])->getKey();
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code] + self::ENTITIES[$code]);
    }
}
