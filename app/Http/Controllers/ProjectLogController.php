<?php

namespace App\Http\Controllers;

use App\Contracts\ContainerRuntime;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectLogController extends Controller
{
    public function __invoke(Request $request, Project $project, ContainerRuntime $runtime): View
    {
        $this->authorize('view', $project);
        abort_unless($project->container_name, 404);
        $lines = min(500, max(20, $request->integer('lines', (int) config('hosting.deployment.log_lines'))));

        try {
            $logs = $runtime->logs($project, $lines);
            $error = null;
        } catch (\Throwable $exception) {
            report($exception);
            $logs = '';
            $error = 'Recent logs are temporarily unavailable.';
        }

        return view('projects.logs', compact('project', 'logs', 'error', 'lines'));
    }
}
