@extends('layouts.app')
@section('title', 'Logs · '.$project->name)
@section('content')
<div class="page-head"><div><div class="eyebrow">Website logs</div><h1>{{ $project->name }}</h1><p class="muted">The latest {{ $lines }} lines from this website container.</p></div><a class="button secondary" href="{{ route('projects.show', $project) }}">Back to project</a></div>
@if($error)<div class="alert" style="border-color:#fecaca;background:#fef2f2;color:#b91c1c">{{ $error }}</div>@endif
<div class="card">
    @if($logs === '')<p class="muted">No container log output is available yet.</p>
    @else<pre style="margin:0;max-height:70vh;overflow:auto;white-space:pre-wrap;word-break:break-word;background:#0f172a;color:#e2e8f0;padding:18px;border-radius:10px">{{ $logs }}</pre>@endif
</div>
@endsection
