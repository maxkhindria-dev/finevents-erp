<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectFinancial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectFinancial>
 */
class ProjectFinancialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'budget' => (string) (fake()->numberBetween(20, 400) * 1000),
        ];
    }
}
