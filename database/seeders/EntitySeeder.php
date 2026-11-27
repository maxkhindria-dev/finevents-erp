<?php

namespace Database\Seeders;

use App\Models\Entity;
use Database\Factories\EntityFactory;
use Illuminate\Database\Seeder;

/** The three FinEvents entities. Safe to run in every environment. */
class EntitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (EntityFactory::ENTITIES as $code => $attributes) {
            Entity::query()->updateOrCreate(['code' => $code], $attributes);
        }
    }
}
