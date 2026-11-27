<?php

namespace App\Policies;

use App\Enums\SensitiveField;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectFinancial;
use Illuminate\Support\Facades\DB;

class ProjectFinancialPolicy
{
    public function view(Person $viewer, ProjectFinancial $financial): bool
    {
        $project = $this->project($financial);

        return $project !== null
            && (new ProjectPolicy)->view($viewer, $project)
            && $viewer->hasSensitiveAccess(SensitiveField::DealValues, $project->entity_id);
    }

    public function update(Person $viewer, ProjectFinancial $financial): bool
    {
        $project = $this->project($financial);

        return $project !== null
            && (new ProjectPolicy)->update($viewer, $project)
            && $viewer->hasSensitiveAccess(SensitiveField::DealValues, $project->entity_id);
    }

    /** Looks the project up directly so the rule doesn't depend on record filters. */
    private function project(ProjectFinancial $financial): ?Project
    {
        $row = DB::table('projects')->where('id', $financial->project_id)->first(['id', 'entity_id']);

        if ($row === null) {
            return null;
        }

        return (new Project)->forceFill(['id' => $row->id, 'entity_id' => $row->entity_id]);
    }
}
