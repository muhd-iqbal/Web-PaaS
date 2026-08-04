@extends('layouts.app')
@section('title', $project->name)
@section('content')
@php
    $plan = $project->user->plan;
    $storageLimitBytes = ($plan?->storage_mb ?? 0) * 1048576;
    $storagePercent = $storageLimitBytes > 0 ? min(100, ($accountStorageBytes / $storageLimitBytes) * 100) : 0;
    $databaseLimitBytes = ($plan?->database_mb ?? 0) * 1048576;
    $databasePercent = $databaseLimitBytes > 0 ? min(100, ($accountDatabaseBytes / $databaseLimitBytes) * 100) : 0;
    $database = $project->hostedDatabase;
@endphp
<div class="page-head"><div><div class="eyebrow">Website project</div><h1>{{ $project->name }}</h1><p class="muted">{{ $project->slug }}</p></div>@if($project->status !== \App\Enums\ProjectStatus::Deploying)<div class="actions"><a class="button secondary" href="{{ route('projects.edit', $project) }}">Edit settings</a><form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Delete this project and all of its files?')">@csrf @method('DELETE')<button class="button danger" type="submit">Delete project</button></form></div>@endif</div>

<div class="grid two" style="margin-bottom:28px">
    <div class="card"><div class="muted small">Project files</div><div class="stat">{{ number_format($project->file_count) }}</div><p class="muted">{{ number_format($project->storage_bytes / 1048576, 2) }} MB used by this website.</p></div>
    <div class="card"><div class="muted small">Account storage</div><div class="stat">{{ number_format($accountStorageBytes / 1048576, 2) }} MB</div><div class="meter"><span style="width:{{ $storagePercent }}%"></span></div><div class="muted small">{{ number_format($storagePercent, 1) }}% of {{ number_format($plan?->storage_mb ?? 0) }} MB</div></div>
</div>

@if($project->runtime === \App\Enums\ProjectRuntime::Php)
<div class="card" style="margin-bottom:28px">
    <div class="eyebrow">Managed database</div><h2>MySQL database</h2>
    @error('database')<div class="error" style="margin-bottom:16px">{{ $message }}</div>@enderror
    @if(!$database)
        @if(($plan?->database_mb ?? 0) > 0)
            <p class="muted">Create one private database for this PHP project. Your plan's {{ number_format($plan->database_mb) }} MB allowance is shared by all databases on the account.</p>
            @if($project->status !== \App\Enums\ProjectStatus::Deploying)<form method="POST" action="{{ route('projects.database.store', $project) }}">@csrf<button class="button" type="submit">Create database</button></form>@endif
        @else<p class="muted">Your current plan does not include database hosting.</p>@endif
    @else
        <div class="grid two" style="margin-bottom:20px">
            <div><div class="muted small">Status</div><strong>{{ $database->status->getLabel() }}</strong></div>
            <div><div class="muted small">Account usage</div><strong>{{ number_format($accountDatabaseBytes / 1048576, 2) }} MB of {{ number_format($plan?->database_mb ?? 0) }} MB</strong></div>
        </div>
        <div class="meter"><span style="width:{{ $databasePercent }}%"></span></div>
        @if($database->status === \App\Enums\ProjectDatabaseStatus::QuotaExceeded)<div class="alert" style="margin-top:16px;border-color:#fed7aa;background:#fff7ed;color:#9a3412">The account database quota is exceeded. Managed databases are read-only until usage is brought within the plan limit.</div>@endif
        @if($database->last_error)<div class="alert" style="margin-top:16px;border-color:#fecaca;background:#fef2f2;color:#b91c1c">{{ $database->last_error }}</div>@endif
        @if(in_array($database->status, [\App\Enums\ProjectDatabaseStatus::Active, \App\Enums\ProjectDatabaseStatus::QuotaExceeded], true))
        <div class="table-wrap" style="margin-top:20px"><table><tbody>
            <tr><th>Host</th><td><code>{{ $database->host }}</code></td></tr>
            <tr><th>Port</th><td><code>{{ $database->port }}</code></td></tr>
            <tr><th>Database</th><td><code>{{ $database->database_name }}</code></td></tr>
            <tr><th>Username</th><td><code>{{ $database->username }}</code></td></tr>
            <tr><th>Password</th><td><code>{{ $database->password }}</code></td></tr>
        </tbody></table></div>
        <p class="muted small">Credentials are injected as standard DB_* environment variables on the next deployment. Keep the password private.</p>
        @endif
        <div class="actions" style="margin-top:18px">
            <form method="POST" action="{{ route('projects.database.refresh', $project) }}">@csrf<button class="button secondary" type="submit">Refresh usage</button></form>
            @if($project->status !== \App\Enums\ProjectStatus::Deploying && $database->status !== \App\Enums\ProjectDatabaseStatus::Failed)<form method="POST" action="{{ route('projects.database.rotate', $project) }}" onsubmit="return confirm('Rotate this password? The website must be redeployed afterward.')">@csrf<button class="button secondary" type="submit">Rotate password</button></form>@endif
            @if($project->status !== \App\Enums\ProjectStatus::Deploying)<form method="POST" action="{{ route('projects.database.destroy', $project) }}" onsubmit="return confirm('Permanently delete this database and all its data?')">@csrf @method('DELETE')<button class="button danger" type="submit">Delete database</button></form>@endif
        </div>
    @endif
</div>
@endif

<div class="card" style="margin-bottom:28px">
    <div class="eyebrow">Deployment</div>
    <div class="page-head"><div><h2>{{ $project->status->label() }}</h2>@if($project->url)<p><a href="{{ $project->url }}" target="_blank" rel="noopener">{{ $project->url }}</a></p>@else<p class="muted">Your public URL will appear after the first successful deployment.</p>@endif</div></div>
    @if($project->last_deployment_error)<div class="alert" style="border-color:#fecaca;background:#fef2f2;color:#b91c1c">{{ $project->last_deployment_error }}</div>@endif
    @error('deployment')<div class="error" style="margin-bottom:16px">{{ $message }}</div>@enderror
    <div class="actions">
        @if($project->file_count > 0 && $project->status !== \App\Enums\ProjectStatus::Deploying)
            <form method="POST" action="{{ route('projects.deploy', $project) }}">@csrf<button class="button" type="submit">{{ $project->deployed_at ? 'Redeploy website' : 'Deploy website' }}</button></form>
        @endif
        @if($project->container_name && $project->status === \App\Enums\ProjectStatus::Active)
            <form method="POST" action="{{ route('projects.restart', $project) }}" onsubmit="return confirm('Restart this website container?')">@csrf<button class="button secondary" type="submit">Restart container</button></form>
        @endif
        @if($project->status === \App\Enums\ProjectStatus::Deploying)<span class="muted">A queued operation is in progress.</span>@endif
    </div>
</div>

<div class="card" style="margin-bottom:28px">
    <div class="eyebrow">Upload website</div><h2>{{ $project->file_count ? 'Replace website files' : 'Upload website files' }}</h2>
    <p class="muted">Upload one ZIP containing a root <strong>{{ $project->runtime->value === 'php' ? 'index.php or index.html' : 'index.html' }}</strong>. A single wrapping folder is removed automatically. A successful upload replaces the current files.</p>
    @if($project->status === \App\Enums\ProjectStatus::Deploying)<p class="muted">File replacement is available when the current operation finishes.</p>
    @else<form method="POST" action="{{ route('projects.files.store', $project) }}" enctype="multipart/form-data">@csrf
        <div class="field"><label for="archive">Website ZIP</label><input id="archive" name="archive" type="file" accept=".zip,application/zip" required><div class="muted small">Maximum ZIP: {{ $plan?->max_upload_mb ?? 0 }} MB · Extracted: {{ $plan?->max_extracted_mb ?? 0 }} MB · Files: {{ number_format($plan?->max_file_count ?? 0) }}</div>@error('archive')<div class="error">{{ $message }}</div>@enderror</div>
        <button class="button" type="submit">Validate and save files</button>
    </form>@endif
</div>

<div class="card" style="margin-bottom:28px"><div class="page-head"><div><h2>Website files</h2><p class="muted">Download individual files or remove files you no longer need.</p></div></div>
    @error('file')<div class="error" style="margin-bottom:16px">{{ $message }}</div>@enderror
    @if($files->isEmpty())<div class="empty"><h3>No website files</h3><p class="muted">Upload a ZIP above to add your website.</p></div>
    @else<div class="table-wrap"><table><thead><tr><th>Path</th><th>Type</th><th>Size</th><th></th></tr></thead><tbody>@foreach($files as $file)<tr><td><strong>{{ $file->path }}</strong></td><td>{{ $file->mime_type }}</td><td>{{ number_format($file->size_bytes / 1024, 1) }} KB</td><td><div class="actions"><a href="{{ route('projects.files.download', [$project, $file]) }}">Download</a>@if($project->status !== \App\Enums\ProjectStatus::Deploying)<form method="POST" action="{{ route('projects.files.destroy', [$project, $file]) }}" onsubmit="return confirm('Delete this file?')">@csrf @method('DELETE')<button class="button danger" type="submit">Delete</button></form>@endif</div></td></tr>@endforeach</tbody></table></div><div style="margin-top:20px">{{ $files->links() }}</div>@endif
</div>

<div class="card" style="margin-bottom:28px"><h2>Deployment history</h2>
    @if($project->deployments->isEmpty())<p class="muted">No deployment operations yet.</p>
    @else<div class="table-wrap"><table><thead><tr><th>Operation</th><th>Status</th><th>Address</th><th>Started</th><th>Finished</th></tr></thead><tbody>@foreach($project->deployments as $deployment)<tr><td>{{ $deployment->type->getLabel() }}</td><td><span class="badge">{{ $deployment->status->getLabel() }}</span></td><td>{{ $deployment->hostname }}</td><td>{{ $deployment->started_at?->format('M j, Y g:i A') ?? 'Queued' }}</td><td>{{ $deployment->completed_at?->format('M j, Y g:i A') ?? '—' }}</td></tr>@endforeach</tbody></table></div>@endif
</div>

<div class="card"><h2>Upload history</h2>
    @if($project->uploads->isEmpty())<p class="muted">No successful uploads yet.</p>
    @else<div class="table-wrap"><table><thead><tr><th>ZIP</th><th>Files</th><th>Extracted size</th><th>Uploaded</th></tr></thead><tbody>@foreach($project->uploads as $upload)<tr><td>{{ $upload->original_name }}</td><td>{{ number_format($upload->file_count) }}</td><td>{{ number_format($upload->extracted_size_bytes / 1048576, 2) }} MB</td><td>{{ $upload->created_at->format('M j, Y g:i A') }}</td></tr>@endforeach</tbody></table></div>@endif
</div>
@endsection
