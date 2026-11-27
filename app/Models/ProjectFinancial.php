<?php

namespace App\Models;

use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use App\Audit\Audited;
use App\Enums\Classification;
use App\Enums\SensitiveField;
use Database\Factories\ProjectFinancialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sensitive project money (budget), kept apart from the project and
 * encrypted at field level. Needs a "deal values" grant for the project's entity.
 *
 * @property int $id
 * @property int $project_id
 * @property string|null $budget
 * @property Classification $classification
 * @property int|null $created_by
 */
class ProjectFinancial extends Model implements HasVisibilityRules
{
    use Audited;

    /** @use HasFactory<ProjectFinancialFactory> */
    use HasFactory;
    use ScopedToViewer;

    /** @var list<string> */
    protected $fillable = ['project_id', 'budget'];

    /**
     * Sensitive tables are read through their own database login in
     * production (layer 3). Locally and in ordinary tests this is the
     * default connection.
     */
    public function getConnectionName(): ?string
    {
        $sensitive = config('finevents.sensitive_connection');

        return is_string($sensitive) && $sensitive !== '' ? $sensitive : parent::getConnectionName();
    }

    /** @var array<string, mixed> */
    protected $attributes = ['classification' => 'restricted'];

    protected function casts(): array
    {
        return [
            'budget' => 'encrypted',
            'classification' => Classification::class,
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The viewer must see the project AND hold the deal-values permission for its entity. */
    public function applyVisibility(Builder $query, Person $viewer): void
    {
        $allowedEntities = $viewer->sensitiveEntityIds(SensitiveField::DealValues);

        $query->whereHas('project', fn (Builder $q) => $q->whereIn('entity_id', $allowedEntities));
    }
}
