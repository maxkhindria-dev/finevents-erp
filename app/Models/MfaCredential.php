<?php

namespace App\Models;

use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use App\Audit\Audited;
use App\Enums\Classification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A person's authenticator-app secret and recovery codes.
 * Only the person themselves can see it: not admins, not the Owner.
 *
 * @property int $id
 * @property int $person_id
 * @property string $type
 * @property string $secret
 * @property int|null $last_used_timestep
 * @property list<string> $recovery_code_hashes
 * @property Carbon|null $confirmed_at
 * @property Classification $classification
 * @property int|null $created_by
 */
class MfaCredential extends Model implements HasVisibilityRules
{
    use Audited;
    use ScopedToViewer;

    /** @var list<string> */
    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = ['type' => 'totp', 'classification' => 'restricted'];

    /** Never copied into the audit log, JSON or logs. @var list<string> */
    protected $hidden = ['secret', 'recovery_code_hashes', 'last_used_timestep'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_code_hashes' => 'array',
            'confirmed_at' => 'datetime',
            'classification' => Classification::class,
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function applyVisibility(Builder $query, Person $viewer): void
    {
        $query->where($this->qualifyColumn('person_id'), $viewer->id);
    }
}
