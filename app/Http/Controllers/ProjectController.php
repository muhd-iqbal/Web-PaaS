<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRuntime;
use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\BandwidthUsage;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('projects.index', [
            'projects' => request()->user()->projects()->latest()->paginate(10),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create', ['runtimes' => ProjectRuntime::cases()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create($request->safe()->only(['name', 'slug', 'runtime']));

        return redirect()->route('projects.show', $project)->with('status', 'Project created. Upload a ZIP when your website files are ready.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project): View
    {
        $this->authorize('view', $project);
        $project->load([
            'uploads' => fn ($query) => $query->latest()->limit(10),
            'deployments' => fn ($query) => $query->latest()->limit(20),
            'hostedDatabase',
            'user.plan',
            'latestResourceSnapshot',
        ]);

        $periodStart = now()->startOfMonth()->toDateString();

        return view('projects.show', [
            'project' => $project,
            'files' => $project->files()->orderBy('path')->paginate(50),
            'accountStorageBytes' => (int) $project->user->projects()->sum('storage_bytes'),
            'accountDatabaseBytes' => (int) $project->user->projects()->withSum('hostedDatabase', 'size_bytes')->get()->sum('hosted_database_sum_size_bytes'),
            'projectBandwidthBytes' => (int) $project->bandwidthUsages()->whereDate('period_start', $periodStart)->sum('bytes_sent'),
            'accountBandwidthBytes' => (int) BandwidthUsage::query()
                ->whereIn('project_id', $project->user->projects()->select('id'))
                ->whereDate('period_start', $periodStart)
                ->sum('bytes_sent'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', ['project' => $project, 'runtimes' => ProjectRuntime::cases()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $attributes = $request->safe()->only(['name', 'slug', 'runtime']);
        $deploymentChanged = $project->slug !== $attributes['slug'] || $project->runtime->value !== $attributes['runtime'];

        if ($deploymentChanged) {
            $attributes['status'] = $project->statusAfterFileChange();
        }

        $project->update($attributes);

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        if ($project->status === ProjectStatus::Deploying) {
            throw ValidationException::withMessages(['deployment' => 'Wait for the current deployment to finish before deleting this project.']);
        }

        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project deleted.');
    }
}
