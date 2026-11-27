<?php

namespace App\Models;

use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use App\Audit\Audited;
use App\Enums\Classification;
use App\Enums\SensitiveField;
use Database\Factories\SensitiveGrantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Permission for one person to see one sensitive field type in one entity.
 *
 * @property int $id
 * @property int $person_id
 * @property SensitiveField $field
 * @property int $entity_id
 * @property int|null $granted_by
 * @property Carbon|null $expires_at
 * @property Classification $classification
 */
class SensitiveGrant extends Model implements HasVisibilityRules
{
    use Audited;

    /** @use HasFactory<SensitiveGrantFactory> */
    use HasFactory;
    use ScopedToViewer;

    /** @var list<string> */
    protected $fillable = ['person_id', 'field', 'entity_id', 'granted_by', 'expires_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['classification' => 'confidential'];

    protected function casts(): array
    {
        return [
            'field' => SensitiveField::class,
            'classification' => Classification::class,
            'expires_at' => 'datetime',
        ];
    }

    public function recordsCreator(): bool
    {
        return false; // granted_by records who granted it
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** Leadership sees all grants; everyone else sees only their own. */
    public function applyVisibility(Builder $query, Person $viewer): void
    {
        if (! $viewer->isLeadership()) {
            $query->where($this->qualifyColumn('person_id'), $viewer->id);
        }
    }
}
