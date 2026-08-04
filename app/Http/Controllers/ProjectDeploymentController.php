<?php

namespace App\Http\Controllers;

use App\Exceptions\DeploymentException;
use App\Models\Project;
use App\Services\DeploymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectDeploymentController extends Controller
{
    public function deploy(Request $request, Project $project, DeploymentManager $manager): RedirectResponse
    {
        $this->authorize('update', $project);

        try {
            $manager->queueDeploy($project, $request->user());
        } catch (DeploymentException $exception) {
            throw ValidationException::withMessages(['deployment' => $exception->getMessage()]);
        }

        return redirect()->route('projects.show', $project)->with('status', 'Deployment queued. The status will update when it finishes.');
    }

    public function restart(Request $request, Project $project, DeploymentManager $manager): RedirectResponse
    {
        $this->authorize('update', $project);

        try {
            $manager->queueRestart($project, $request->user());
        } catch (DeploymentException $exception) {
            throw ValidationException::withMessages(['deployment' => $exception->getMessage()]);
        }

        return redirect()->route('projects.show', $project)->with('status', 'Container restart queued.');
    }
}
