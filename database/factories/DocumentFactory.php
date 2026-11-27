<?php

namespace Database\Factories;

use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'drive_file_id' => 'test-'.fake()->unique()->bothify('????????????'),
            'project_id' => Project::factory(),
            'client_id' => null,
            'type' => fake()->randomElement(['execution_plan', 'agenda', 'minutes', 'running_order']),
            'title' => fake()->sentence(3),
            'visibility' => DocumentVisibility::Internal,
        ];
    }

    public function freelancersAllowed(): static
    {
        return $this->state(['visibility' => DocumentVisibility::FreelancersAllowed]);
    }
}
