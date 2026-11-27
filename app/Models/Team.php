<?php

namespace App\Models;

use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use App\Enums\Classification;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property string $name
 * @property int|null $lead_person_id
 * @property Classification $classification
 * @property int|null $created_by
 */
class Team extends Model implements HasVisibilityRules
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    use ScopedToViewer;

    /** @var list<string> */
    protected $fillable = ['entity_id', 'name', 'lead_person_id'];

    /** @var array<string, mixed> */
    protected $attributes = ['classification' => 'internal'];

    protected function casts(): array
    {
        return ['classification' => Classification::class];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function applyVisibility(Builder $query, Person $viewer): void
    {
        if (! $viewer->isLeadership()) {
            $query->whereIn($this->qualifyColumn('entity_id'), $viewer->visibleEntityIds());
        }
    }
}
