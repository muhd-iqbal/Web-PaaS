@extends('layouts.app')
@section('title', 'Projects')
@section('content')
<div class="page-head"><div><div class="eyebrow">Websites</div><h1>Your projects</h1><p class="muted">Create and organize the websites in your account.</p></div><a class="button" href="{{ route('projects.create') }}">Create website</a></div>
<div class="card">
    @if($projects->isEmpty())<div class="empty"><h3>No projects yet</h3><p class="muted">Create a project and choose its runtime.</p><a class="button" href="{{ route('projects.create') }}">Create website</a></div>
    @else<div class="table-wrap"><table><thead><tr><th>Website</th><th>Runtime</th><th>Files</th><th>Storage</th><th>Created</th><th></th></tr></thead><tbody>@foreach($projects as $project)<tr><td><strong>{{ $project->name }}</strong><div class="muted small">{{ $project->slug }}</div></td><td>{{ $project->runtime->label() }}</td><td>{{ number_format($project->file_count) }}</td><td>{{ number_format($project->storage_bytes / 1048576, 2) }} MB</td><td>{{ $project->created_at->format('M j, Y') }}</td><td><a href="{{ route('projects.show', $project) }}">Manage</a></td></tr>@endforeach</tbody></table></div><div style="margin-top:20px">{{ $projects->links() }}</div>@endif
</div>
@endsection
