<?php

namespace Tests\Support;

use App\Access\AccessContext;
use App\Enums\SensitiveField;
use App\Models\Client;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectFinancial;
use App\Models\SensitiveGrant;
use Illuminate\Database\Eloquent\Model;

/**
 * A small, fully generated FinEvents used by the permission tests.
 *
 * Entities: FR, UK, US. Each has an "assigned" project A_xx (client CA_xx) and
 * an "unassigned" project X_xx (client CX_xx).
 *
 * People (primary entity in brackets):
 *   owner (FR), super_admin (UK)
 *   admin_fr (FR)
 *   admin_uk (UK, plus extra access to US, deal-values grant for UK that has EXPIRED)
 *   user_fr (FR): member of A_FR, direct member of client CX_FR, deal-values grant for UK
 *   freelancer_fr (FR): member of A_FR
 *   freelancer_expired (FR): member of A_FR, access expired yesterday
 *   user_suspended (FR): member of A_FR, suspended
 *   user_uk (UK): no assignments
 * admin_fr holds a deal-values grant for FR.
 *
 * Documents: D_A_FR_internal, D_A_FR_free (project A_FR), D_X_UK_internal (project X_UK),
 *            D_CX_FR_internal, D_CA_FR_free (client-level documents).
 */
final class AccessWorld
{
    /** @var array<string, Entity> */
    public array $entities = [];

    /** @var array<string, Person> */
    public array $people = [];

    /** @var array<string, Client> */
    public array $clients = [];

    /** @var array<string, Project> */
    public array $projects = [];

    /** @var array<string, ProjectFinancial> */
    public array $financials = [];

    /** @var array<string, Document> */
    public array $documents = [];

    public static function build(): self
    {
        return app(AccessContext::class)->runAsSystem(function (): self {
            $w = new self;

            foreach (['FR', 'UK', 'US'] as $code) {
                $w->entities[$code] = Entity::factory()->code($code)->create();
            }

            [$fr, $uk, $us] = [$w->entities['FR'], $w->entities['UK'], $w->entities['US']];

            $w->people = [
                'owner' => Person::factory()->owner()->in($fr)->create(),
                'super_admin' => Person::factory()->superAdmin()->in($uk)->create(),
                'admin_fr' => Person::factory()->admin()->in($fr)->create(),
                'admin_uk' => Person::factory()->admin()->in($uk)->create(),
                'user_fr' => Person::factory()->in($fr)->create(),
                'freelancer_fr' => Person::factory()->freelancer()->in($fr)->create(),
                'freelancer_expired' => Person::factory()->freelancer('-1 day')->in($fr)->create(),
                'user_suspended' => Person::factory()->suspended()->in($fr)->create(),
                'user_uk' => Person::factory()->in($uk)->create(),
            ];

            $w->people['admin_uk']->extraEntities()->attach($us->id);

            foreach ($w->entities as $code => $entity) {
                foreach (['A', 'X'] as $kind) {
                    $client = Client::factory()->create(['entity_id' => $entity->id, 'name' => "C{$kind}_{$code}"]);
                    $project = Project::factory()->in($entity, $client)->create(['name' => "{$kind}_{$code}"]);
                    $w->clients["C{$kind}_{$code}"] = $client;
                    $w->projects["{$kind}_{$code}"] = $project;
                    $w->financials["{$kind}_{$code}"] = ProjectFinancial::factory()->create(['project_id' => $project->id]);
                }
            }

            $w->projects['A_FR']->members()->attach([
                $w->people['user_fr']->id,
                $w->people['freelancer_fr']->id,
                $w->people['freelancer_expired']->id,
                $w->people['user_suspended']->id,
            ]);
            $w->clients['CX_FR']->members()->attach($w->people['user_fr']->id);

            SensitiveGrant::factory()->create(['person_id' => $w->people['admin_fr']->id, 'entity_id' => $fr->id, 'field' => SensitiveField::DealValues]);
            SensitiveGrant::factory()->create(['person_id' => $w->people['user_fr']->id, 'entity_id' => $uk->id, 'field' => SensitiveField::DealValues]);
            SensitiveGrant::factory()->create(['person_id' => $w->people['admin_uk']->id, 'entity_id' => $uk->id, 'field' => SensitiveField::DealValues, 'expires_at' => now()->subDay()]);

            $w->documents = [
                'D_A_FR_internal' => Document::factory()->create(['project_id' => $w->projects['A_FR']->id]),
                'D_A_FR_free' => Document::factory()->freelancersAllowed()->create(['project_id' => $w->projects['A_FR']->id]),
                'D_X_UK_internal' => Document::factory()->create(['project_id' => $w->projects['X_UK']->id]),
                'D_CX_FR_internal' => Document::factory()->create(['project_id' => null, 'client_id' => $w->clients['CX_FR']->id]),
                'D_CA_FR_free' => Document::factory()->freelancersAllowed()->create(['project_id' => null, 'client_id' => $w->clients['CA_FR']->id]),
            ];

            return $w;
        });
    }

    public function person(string $name): Person
    {
        // Fresh copy from the database, as a real request would load it.
        return app(AccessContext::class)->runAsSystem(
            fn () => Person::query()->findOrFail($this->people[$name]->id)
        );
    }

    /**
     * Layer 2: names of the records a query returns when run as $viewer
     * (or as nobody, when $viewer is null).
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, Model>  $named
     * @return list<string>
     */
    public function namesVisibleByQuery(?Person $viewer, string $modelClass, array $named): array
    {
        $run = fn () => $modelClass::query()->pluck((new $modelClass)->getKeyName())->all();
        $ids = $viewer === null ? $run() : app(AccessContext::class)->runAs($viewer, $run);

        return $this->namesFor($named, $ids);
    }

    /**
     * Layer 1: names of the records the policy allows $viewer to view.
     *
     * @param  array<string, Model>  $named
     * @return list<string>
     */
    public function namesAllowedByPolicy(?Person $viewer, array $named): array
    {
        $allowed = [];

        foreach ($named as $name => $model) {
            $gate = $viewer === null ? app('Illuminate\Contracts\Auth\Access\Gate') : app('Illuminate\Contracts\Auth\Access\Gate')->forUser($viewer);
            if ($gate->allows('view', $model)) {
                $allowed[] = $name;
            }
        }

        sort($allowed);

        return $allowed;
    }

    /**
     * @param  array<string, Model>  $named
     * @param  array<int, mixed>  $ids
     * @return list<string>
     */
    private function namesFor(array $named, array $ids): array
    {
        $names = [];

        foreach ($named as $name => $model) {
            if (in_array($model->getKey(), $ids)) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }
}
