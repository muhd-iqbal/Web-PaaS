<?php

namespace App\Observers;

use App\Jobs\DestroyProjectContainer;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;

class ProjectObserver
{
    /**
     * Handle the Project "created" event.
     */
    public function created(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "updated" event.
     */
    public function updated(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "deleted" event.
     */
    public function deleted(Project $project): void
    {
        Storage::disk(config('hosting.project_disk'))->deleteDirectory($project->storageDirectory());

        if ($project->container_name || $project->deployed_at || $project->last_deployment_error) {
            DestroyProjectContainer::dispatch($project->id);
        }
    }

    /**
     * Handle the Project "restored" event.
     */
    public function restored(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "force deleted" event.
     */
    public function forceDeleted(Project $project): void
    {
        //
    }
}
