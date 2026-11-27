<?php

namespace Database\Seeders;

use App\Enums\SensitiveField;
use App\Models\Client;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectFinancial;
use App\Models\SensitiveGrant;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Generated demo data for local development and staging only.
 * Every name and email is invented (@example.test). Real accounts are
 * created later with a dedicated command, never by this seeder.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be created in production.');
        }

        $entities = Entity::query()->whereIn('code', ['FR', 'UK', 'US'])->get()->keyBy('code');
        $france = $entities->get('FR') ?? throw new RuntimeException('Run EntitySeeder first.');

        Person::factory()->owner()->in($france)->create(['full_name' => 'Demo Owner', 'email' => 'owner@example.test']);
        Person::factory()->superAdmin()->in($france)->create(['full_name' => 'Demo Super Admin', 'email' => 'superadmin@example.test']);

        foreach ($entities as $code => $entity) {
            $lower = strtolower($code);
            $admin = Person::factory()->admin()->in($entity)->create(['full_name' => "Demo Admin {$code}", 'email' => "admin.{$lower}@example.test"]);
            $user = Person::factory()->in($entity)->create(['full_name' => "Demo User {$code}", 'email' => "user.{$lower}@example.test"]);
            $freelancer = Person::factory()->freelancer()->in($entity)->create(['full_name' => "Demo Freelancer {$code}", 'email' => "freelancer.{$lower}@example.test"]);

            SensitiveGrant::factory()->create(['person_id' => $admin->id, 'entity_id' => $entity->id, 'field' => SensitiveField::DealValues]);

            foreach (range(1, 3) as $i) {
                $client = Client::factory()->create(['entity_id' => $entity->id]);
                $project = Project::factory()->in($entity, $client)->create();
                ProjectFinancial::factory()->create(['project_id' => $project->id]);
                Document::factory()->create(['project_id' => $project->id]);
                Document::factory()->freelancersAllowed()->create(['project_id' => $project->id]);

                if ($i === 1) {
                    $project->members()->attach([$user->id, $freelancer->id]);
                }
            }
        }
    }
}
