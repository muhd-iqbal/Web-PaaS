<?php

namespace App\Http\Controllers;

use App\Exceptions\DatabaseHostingException;
use App\Models\Project;
use App\Services\ProjectDatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ProjectDatabaseController extends Controller
{
    public function store(Project $project, ProjectDatabaseManager $manager): RedirectResponse
    {
        $this->authorize('update', $project);

        try {
            $manager->provision($project);
        } catch (DatabaseHostingException $exception) {
            throw ValidationException::withMessages(['database' => $exception->getMessage()]);
        }

        return to_route('projects.show', $project)->with('status', 'Database created. Redeploy the website to apply its credentials.');
    }

    public function rotate(Project $project, ProjectDatabaseManager $manager): RedirectResponse
    {
        $this->authorize('update', $project);
        $database = $project->hostedDatabase()->firstOrFail();

        try {
            $manager->rotatePassword($database);
        } catch (DatabaseHostingException $exception) {
            throw ValidationException::withMessages(['database' => $exception->getMessage()]);
        }

        return to_route('projects.show', $project)->with('status', 'Database password rotated. Redeploy the website to apply it.');
    }

    public function refresh(Project $project, ProjectDatabaseManager $manager): RedirectResponse
    {
        $this->authorize('view', $project);

        try {
            $manager->refreshUsageForUser($project->user);
        } catch (DatabaseHostingException $exception) {
            throw ValidationException::withMessages(['database' => $exception->getMessage()]);
        }

        return to_route('projects.show', $project)->with('status', 'Database usage refreshed.');
    }

    public function destroy(Project $project, ProjectDatabaseManager $manager): RedirectResponse
    {
        $this->authorize('update', $project);
        $database = $project->hostedDatabase()->firstOrFail();

        try {
            $manager->destroy($database);
        } catch (DatabaseHostingException $exception) {
            throw ValidationException::withMessages(['database' => $exception->getMessage()]);
        }

        return to_route('projects.show', $project)->with('status', 'Managed database deleted.');
    }
}
