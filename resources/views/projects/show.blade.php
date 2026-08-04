@extends('layouts.app')
@section('title', $project->name)
@section('content')
<div class="page-head"><div><div class="eyebrow">Website project</div><h1>{{ $project->name }}</h1><p class="muted">{{ $project->slug }}</p></div><div class="actions"><a class="button secondary" href="{{ route('projects.edit', $project) }}">Edit</a><form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?')">@csrf @method('DELETE')<button class="button danger" type="submit">Delete</button></form></div></div>
<div class="grid two">
    <div class="card"><div class="muted small">Status</div><div class="stat">{{ $project->status->label() }}</div><p class="muted">The project record is ready. ZIP uploads will be implemented in Phase 2.</p></div>
    <div class="card"><div class="muted small">Website type</div><div class="stat">{{ $project->runtime->label() }}</div><p class="muted">Runtime configuration and containers will be implemented in Phase 3.</p></div>
</div>
@endsection
