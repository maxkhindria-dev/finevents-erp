<?php

namespace App\Policies;

use App\Enums\DocumentVisibility;
use App\Models\Client;
use App\Models\Document;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class DocumentPolicy
{
    public function view(Person $viewer, Document $document): bool
    {
        if ($viewer->isFreelancer() && $document->visibility !== DocumentVisibility::FreelancersAllowed) {
            return false;
        }

        if ($document->project_id !== null) {
            $project = $this->project($document->project_id);

            return $project !== null && (new ProjectPolicy)->view($viewer, $project);
        }

        if ($document->client_id !== null) {
            $client = $this->client($document->client_id);

            return $client !== null && (new ClientPolicy)->view($viewer, $client);
        }

        return false; // a document must belong to a project or a client
    }

    public function update(Person $viewer, Document $document): bool
    {
        if ($document->project_id !== null) {
            $project = $this->project($document->project_id);

            return $project !== null && (new ProjectPolicy)->update($viewer, $project);
        }

        if ($document->client_id !== null) {
            $client = $this->client($document->client_id);

            return $client !== null && (new ClientPolicy)->update($viewer, $client);
        }

        return false;
    }

    public function delete(Person $viewer, Document $document): bool
    {
        return $viewer->isLeadership();
    }

    // Parents are read directly so these rules don't depend on record filters.

    private function project(int $id): ?Project
    {
        $row = DB::table('projects')->where('id', $id)->first(['id', 'entity_id']);

        return $row === null ? null : (new Project)->forceFill(['id' => $row->id, 'entity_id' => $row->entity_id]);
    }

    private function client(int $id): ?Client
    {
        $row = DB::table('clients')->where('id', $id)->first(['id', 'entity_id']);

        return $row === null ? null : (new Client)->forceFill(['id' => $row->id, 'entity_id' => $row->entity_id]);
    }
}
