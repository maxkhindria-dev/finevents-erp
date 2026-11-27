<?php

namespace App\Models;

use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use App\Audit\Audited;
use App\Enums\Classification;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * One project per event.
 *
 * @property int $id
 * @property string $code
 * @property int $client_id
 * @property int $entity_id
 * @property string $name
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $venue
 * @property ProjectStatus $status
 * @property string|null $drive_folder_id
 * @property Classification $classification
 * @property int|null $created_by
 */
class Project extends Model implements HasVisibilityRules
{
    use Audited;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;
    use ScopedToViewer;

    /** Project codes look like FR-2026-014: entity, year, sequence. */
    public const CODE_PATTERN = '/^(FR|UK|US)-\d{4}-\d{3}$/';

    /** @var list<string> */
    protected $fillable = [
        'code', 'client_id', 'entity_id', 'name', 'starts_on', 'ends_on', 'venue', 'status', 'drive_folder_id',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'planned', 'classification' => 'restricted'];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'classification' => Classification::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsToMany<Person, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'project_members')
            ->withPivot('role_on_project')->withTimestamps();
    }

    /** @return HasOne<ProjectFinancial, $this> */
    public function financials(): HasOne
    {
        return $this->hasOne(ProjectFinancial::class);
    }

    /**
     * Leadership: all projects. Admins: projects in their entities.
     * Everyone: projects they are a member of.
     */
    public function applyVisibility(Builder $query, Person $viewer): void
    {
        if ($viewer->isLeadership()) {
            return;
        }

        $query->where(function (Builder $q) use ($viewer) {
            if ($viewer->role === Role::Admin) {
                $q->whereIn($this->qualifyColumn('entity_id'), $viewer->visibleEntityIds());
            }

            $q->orWhereExists(fn (QueryBuilder $sub) => $sub->from('project_members')
                ->whereColumn('project_members.project_id', $this->qualifyColumn('id'))
                ->where('project_members.person_id', $viewer->id));
        });
    }
}
