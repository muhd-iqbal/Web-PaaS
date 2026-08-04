<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRuntime;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
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
            'user.plan',
        ]);

        return view('projects.show', [
            'project' => $project,
            'files' => $project->files()->orderBy('path')->paginate(50),
            'accountStorageBytes' => (int) $project->user->projects()->sum('storage_bytes'),
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
        $project->update($request->safe()->only(['name', 'slug', 'runtime']));

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project deleted.');
    }
}
