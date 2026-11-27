<?php

namespace App\Models;

use App\Access\HasVisibilityRules;
use App\Access\ScopedToViewer;
use App\Enums\Classification;
use App\Enums\DocumentVisibility;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata for a Google Drive file. The file itself stays in Drive.
 *
 * @property int $id
 * @property string $drive_file_id
 * @property int|null $project_id
 * @property int|null $client_id
 * @property string $type
 * @property string $title
 * @property DocumentVisibility $visibility
 * @property Classification $classification
 * @property int|null $created_by
 */
class Document extends Model implements HasVisibilityRules
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use ScopedToViewer;

    /** @var list<string> */
    protected $fillable = ['drive_file_id', 'project_id', 'client_id', 'type', 'title', 'visibility'];

    /** @var array<string, mixed> */
    protected $attributes = ['visibility' => 'internal', 'classification' => 'confidential'];

    protected function casts(): array
    {
        return [
            'visibility' => DocumentVisibility::class,
            'classification' => Classification::class,
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Visible when the viewer can see its project (or client), and, for
     * freelancers, only if the document is marked "freelancers allowed".
     * The project/client checks reuse those models' own filters.
     */
    public function applyVisibility(Builder $query, Person $viewer): void
    {
        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $p) => $p->whereNotNull('project_id')->whereHas('project'))
            ->orWhere(fn (Builder $c) => $c->whereNull('project_id')->whereNotNull('client_id')->whereHas('client')));

        if ($viewer->isFreelancer()) {
            $query->where($this->qualifyColumn('visibility'), DocumentVisibility::FreelancersAllowed->value);
        }
    }
}
