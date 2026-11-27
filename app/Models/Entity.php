<?php

namespace App\Models;

use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use App\Audit\Audited;
use App\Enums\Classification;
use Database\Factories\EntityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A FinEvents legal entity: France, UK or USA.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $country
 * @property string $currency
 * @property string $accounting_system
 * @property Classification $classification
 */
class Entity extends Model implements HasVisibilityRules
{
    use Audited;

    /** @use HasFactory<EntityFactory> */
    use HasFactory;

    use ScopedToViewer;

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'country', 'currency', 'accounting_system'];

    /** @var array<string, mixed> */
    protected $attributes = ['classification' => 'internal'];

    protected function casts(): array
    {
        return ['classification' => Classification::class];
    }

    public function recordsCreator(): bool
    {
        return false;
    }

    /** Every active person may see the list of entities (names only, internal). */
    public function applyVisibility(Builder $query, Person $viewer): void
    {
        // No extra filter: inactive viewers are already blocked by the scope.
    }
}
