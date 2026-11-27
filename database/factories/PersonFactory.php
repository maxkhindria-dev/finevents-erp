<?php

namespace Database\Factories;

use App\Enums\PersonKind;
use App\Enums\PersonStatus;
use App\Enums\Role;
use App\Models\Entity;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Generated people only. Emails use the reserved example.test domain.
 *
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->userName().'@example.test',
            'full_name' => fake()->name(),
            'role' => Role::User,
            'primary_entity_id' => fn () => EntityFactory::defaultId(),
            'kind' => PersonKind::Employee,
            'status' => PersonStatus::Active,
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(['role' => $role]);
    }

    public function owner(): static
    {
        return $this->role(Role::Owner);
    }

    public function superAdmin(): static
    {
        return $this->role(Role::SuperAdmin);
    }

    public function admin(): static
    {
        return $this->role(Role::Admin);
    }

    public function in(Entity $entity): static
    {
        return $this->state(['primary_entity_id' => $entity->id]);
    }

    public function freelancer(?string $expires = '+90 days'): static
    {
        return $this->state([
            'kind' => PersonKind::Freelancer,
            'access_expires_at' => $expires === null ? null : now()->modify($expires),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => PersonStatus::Suspended]);
    }

    public function offboarded(): static
    {
        return $this->state(['status' => PersonStatus::Offboarded]);
    }
}
