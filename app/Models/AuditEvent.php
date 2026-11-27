<?php

namespace App\Models;

use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One entry in the audit log. Read-only: entries are written only by
 * App\Audit\AuditLogger, and the database refuses edits and deletions.
 *
 * @property int $id
 * @property int $seq
 * @property string $occurred_at
 * @property string $actor_type
 * @property int|null $actor_person_id
 * @property string $action
 * @property string|null $target_type
 * @property int|null $target_id
 * @property int|null $entity_id
 * @property string|null $before
 * @property string|null $after
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $country
 * @property string $prev_hash
 * @property string $hash
 */
class AuditEvent extends Model implements HasVisibilityRules
{
    use ScopedToViewer;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    protected static function booted(): void
    {
        $refuse = fn () => throw new LogicException('Audit events are written only by AuditLogger and can never be changed or deleted.');

        static::creating($refuse);
        static::updating($refuse);
        static::deleting($refuse);
    }

    public function recordsCreator(): bool
    {
        return false;
    }

    /** Owner and Super Admins only (CLAUDE.md §2). */
    public function applyVisibility(Builder $query, Person $viewer): void
    {
        if (! $viewer->isLeadership()) {
            $query->whereRaw('1 = 0');
        }
    }
}
