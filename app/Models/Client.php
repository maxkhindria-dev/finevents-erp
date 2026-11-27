<?php

namespace App\Models;

use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use App\Enums\Classification;
use App\Enums\Role;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * A client company. Client data is classified "restricted".
 *
 * @property int $id
 * @property int $entity_id
 * @property string $name
 * @property string|null $sector
 * @property int|null $owner_person_id
 * @property string|null $notes
 * @property Classification $classification
 * @property int|null $created_by
 */
class Client extends Model implements HasVisibilityRules
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use ScopedToViewer;

    /** @var list<string> */
    protected $fillable = ['entity_id', 'name', 'sector', 'owner_person_id', 'notes'];

    /** @var array<string, mixed> */
    protected $attributes = ['classification' => 'restricted'];

    protected function casts(): array
    {
        return ['classification' => Classification::class];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return BelongsToMany<Person, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'client_members')->withTimestamps();
    }

    /**
     * Leadership: all clients. Admins: clients in their entities.
     * Everyone: clients they are a member of, directly or through a project.
     */
    public function applyVisibility(Builder $query, Person $viewer): void
    {
        if ($viewer->isLeadership()) {
            return;
        }

        $id = $this->qualifyColumn('id');

        $query->where(function (Builder $q) use ($viewer, $id) {
            if ($viewer->role === Role::Admin) {
                $q->whereIn($this->qualifyColumn('entity_id'), $viewer->visibleEntityIds());
            }

            $q->orWhereExists(fn (QueryBuilder $sub) => $sub->from('client_members')
                ->whereColumn('client_members.client_id', $id)
                ->where('client_members.person_id', $viewer->id));

            $q->orWhereExists(fn (QueryBuilder $sub) => $sub->from('project_members')
                ->join('projects as member_projects', 'member_projects.id', '=', 'project_members.project_id')
                ->whereColumn('member_projects.client_id', $id)
                ->where('project_members.person_id', $viewer->id));
        });
    }
}
