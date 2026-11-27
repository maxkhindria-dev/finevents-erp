<?php

namespace App\Models;

use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use App\Enums\Classification;
use App\Enums\PersonKind;
use App\Enums\PersonStatus;
use App\Enums\Role;
use App\Enums\SensitiveField;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A FinEvents staff member or freelancer, and the sign-in identity.
 * There are no passwords: sign-in is Google Workspace only.
 *
 * @property int $id
 * @property string|null $google_id
 * @property string $email
 * @property string $full_name
 * @property Role $role
 * @property int $primary_entity_id
 * @property int|null $team_id
 * @property PersonKind $kind
 * @property Carbon|null $access_expires_at
 * @property PersonStatus $status
 * @property Carbon|null $last_login_at
 * @property Classification $classification
 * @property int|null $created_by
 */
class Person extends Authenticatable implements HasVisibilityRules
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    use ScopedToViewer;

    protected $table = 'people';

    /**
     * role and status are deliberately NOT mass-assignable: they change only
     * through explicit, permission-checked actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email', 'full_name', 'primary_entity_id', 'team_id', 'kind', 'access_expires_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'role' => 'user',
        'kind' => 'employee',
        'status' => 'active',
        'classification' => 'confidential',
    ];

    /** @var list<string> */
    protected $hidden = ['google_id', 'owner_slot'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'kind' => PersonKind::class,
            'status' => PersonStatus::class,
            'classification' => Classification::class,
            'access_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    // --- Sign-in: no passwords and no "remember me" ------------------------

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    // --- Relations ---------------------------------------------------------

    /** @return BelongsTo<Entity, $this> */
    public function primaryEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'primary_entity_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsToMany<Entity, $this> */
    public function extraEntities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'person_entity_access')->withTimestamps();
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('role_on_project')->withTimestamps();
    }

    /** @return HasMany<SensitiveGrant, $this> */
    public function sensitiveGrants(): HasMany
    {
        return $this->hasMany(SensitiveGrant::class);
    }

    // --- Access questions used by scopes and policies ----------------------
    // These read the person's own access rows directly, so they never depend
    // on the record filters they help to build.

    public function isActive(): bool
    {
        if ($this->status !== PersonStatus::Active) {
            return false;
        }

        return $this->access_expires_at === null || $this->access_expires_at->isFuture();
    }

    public function isLeadership(): bool
    {
        return $this->role->isLeadership();
    }

    public function isFreelancer(): bool
    {
        return $this->kind === PersonKind::Freelancer;
    }

    /**
     * Entities this person can work in: everything for leadership, otherwise
     * their primary entity plus any extra entities granted to them.
     *
     * @return list<int>
     */
    public function visibleEntityIds(): array
    {
        if ($this->isLeadership()) {
            return array_values(DB::table('entities')->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        $extra = DB::table('person_entity_access')->where('person_id', $this->id)->pluck('entity_id');

        return array_values($extra->push($this->primary_entity_id)->map(fn ($id) => (int) $id)->unique()->all());
    }

    public function canSeeEntity(int $entityId): bool
    {
        return in_array($entityId, $this->visibleEntityIds(), true);
    }

    /**
     * Entities in which this person may see a sensitive field type.
     * Leadership sees sensitive fields everywhere; others need an unexpired grant.
     *
     * @return list<int>
     */
    public function sensitiveEntityIds(SensitiveField $field): array
    {
        if ($this->isLeadership()) {
            return $this->visibleEntityIds();
        }

        return array_values(DB::table('sensitive_grants')
            ->where('person_id', $this->id)
            ->where('field', $field->value)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('entity_id')->map(fn ($id) => (int) $id)->all());
    }

    public function hasSensitiveAccess(SensitiveField $field, int $entityId): bool
    {
        return in_array($entityId, $this->sensitiveEntityIds($field), true);
    }

    public function isProjectMember(int $projectId): bool
    {
        return DB::table('project_members')
            ->where('project_id', $projectId)->where('person_id', $this->id)->exists();
    }

    /** Member of the client directly, or of any of the client's projects. */
    public function isClientMember(int $clientId): bool
    {
        return DB::table('client_members')->where('client_id', $clientId)->where('person_id', $this->id)->exists()
            || DB::table('project_members')
                ->join('projects', 'projects.id', '=', 'project_members.project_id')
                ->where('projects.client_id', $clientId)
                ->where('project_members.person_id', $this->id)
                ->exists();
    }

    // --- Layer 2: which people rows this viewer may see ------------------

    public function applyVisibility(Builder $query, Person $viewer): void
    {
        if ($viewer->isLeadership()) {
            return;
        }

        if ($viewer->role === Role::Admin) {
            $query->where(fn (Builder $q) => $q
                ->whereIn($this->qualifyColumn('primary_entity_id'), $viewer->visibleEntityIds())
                ->orWhere($this->qualifyColumn('id'), $viewer->id));

            return;
        }

        $query->where($this->qualifyColumn('id'), $viewer->id);
    }
}
