@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-head"><div><div class="eyebrow">Control panel</div><h1>Hello, {{ auth()->user()->name }}</h1><p class="muted">Manage your website projects without worrying about servers.</p></div><a class="button" href="{{ route('projects.create') }}">Create website</a></div>
<div class="grid" style="margin-bottom:28px">
    <div class="card"><div class="muted small">Your plan</div><div class="stat">{{ auth()->user()->plan?->name ?? 'No plan' }}</div><div class="muted small">{{ $projectCount }} of {{ auth()->user()->plan?->website_limit ?? 0 }} websites used</div></div>
    <div class="card"><div class="muted small">Websites</div><div class="stat">{{ $projectCount }}</div><div class="muted small">Total projects</div></div>
    <div class="card"><div class="muted small">Live websites</div><div class="stat">{{ $liveProjects }}</div><div class="muted small">Projects currently deployed</div></div>
</div>
<div class="card"><div class="page-head"><div><h2>Recent projects</h2><p class="muted">Your latest website projects.</p></div><a href="{{ route('projects.index') }}">View all</a></div>
    @if($projects->isEmpty())<div class="empty"><h3>No projects yet</h3><p class="muted">Create your first website project to get started.</p><a class="button" href="{{ route('projects.create') }}">Create website</a></div>
    @else<div class="table-wrap"><table><thead><tr><th>Name</th><th>Runtime</th><th>Status</th><th></th></tr></thead><tbody>@foreach($projects as $project)<tr><td><strong>{{ $project->name }}</strong><div class="muted small">{{ $project->slug }}</div></td><td>{{ $project->runtime->label() }}</td><td><span class="badge">{{ $project->status->label() }}</span></td><td><a href="{{ route('projects.show', $project) }}">Manage</a></td></tr>@endforeach</tbody></table></div>@endif
</div>
@endsection
