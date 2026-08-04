@extends('layouts.app')
@section('title', $project->name)
@section('content')
@php
    $plan = $project->user->plan;
    $storageLimitBytes = ($plan?->storage_mb ?? 0) * 1048576;
    $storagePercent = $storageLimitBytes > 0 ? min(100, ($accountStorageBytes / $storageLimitBytes) * 100) : 0;
@endphp
<div class="page-head"><div><div class="eyebrow">Website project</div><h1>{{ $project->name }}</h1><p class="muted">{{ $project->slug }}</p></div><div class="actions"><a class="button secondary" href="{{ route('projects.edit', $project) }}">Edit settings</a><form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Delete this project and all of its files?')">@csrf @method('DELETE')<button class="button danger" type="submit">Delete project</button></form></div></div>

<div class="grid two" style="margin-bottom:28px">
    <div class="card"><div class="muted small">Project files</div><div class="stat">{{ number_format($project->file_count) }}</div><p class="muted">{{ number_format($project->storage_bytes / 1048576, 2) }} MB used by this website.</p></div>
    <div class="card"><div class="muted small">Account storage</div><div class="stat">{{ number_format($accountStorageBytes / 1048576, 2) }} MB</div><div class="meter"><span style="width:{{ $storagePercent }}%"></span></div><div class="muted small">{{ number_format($storagePercent, 1) }}% of {{ number_format($plan?->storage_mb ?? 0) }} MB</div></div>
</div>

<div class="card" style="margin-bottom:28px">
    <div class="eyebrow">Upload website</div><h2>{{ $project->file_count ? 'Replace website files' : 'Upload website files' }}</h2>
    <p class="muted">Upload one ZIP containing a root <strong>{{ $project->runtime->value === 'php' ? 'index.php or index.html' : 'index.html' }}</strong>. A single wrapping folder is removed automatically. A successful upload replaces the current files.</p>
    <form method="POST" action="{{ route('projects.files.store', $project) }}" enctype="multipart/form-data">@csrf
        <div class="field"><label for="archive">Website ZIP</label><input id="archive" name="archive" type="file" accept=".zip,application/zip" required><div class="muted small">Maximum ZIP: {{ $plan?->max_upload_mb ?? 0 }} MB · Extracted: {{ $plan?->max_extracted_mb ?? 0 }} MB · Files: {{ number_format($plan?->max_file_count ?? 0) }}</div>@error('archive')<div class="error">{{ $message }}</div>@enderror</div>
        <button class="button" type="submit">Validate and save files</button>
    </form>
</div>

<div class="card" style="margin-bottom:28px"><div class="page-head"><div><h2>Website files</h2><p class="muted">Download individual files or remove files you no longer need.</p></div></div>
    @error('file')<div class="error" style="margin-bottom:16px">{{ $message }}</div>@enderror
    @if($files->isEmpty())<div class="empty"><h3>No website files</h3><p class="muted">Upload a ZIP above to add your website.</p></div>
    @else<div class="table-wrap"><table><thead><tr><th>Path</th><th>Type</th><th>Size</th><th></th></tr></thead><tbody>@foreach($files as $file)<tr><td><strong>{{ $file->path }}</strong></td><td>{{ $file->mime_type }}</td><td>{{ number_format($file->size_bytes / 1024, 1) }} KB</td><td><div class="actions"><a href="{{ route('projects.files.download', [$project, $file]) }}">Download</a><form method="POST" action="{{ route('projects.files.destroy', [$project, $file]) }}" onsubmit="return confirm('Delete this file?')">@csrf @method('DELETE')<button class="button danger" type="submit">Delete</button></form></div></td></tr>@endforeach</tbody></table></div><div style="margin-top:20px">{{ $files->links() }}</div>@endif
</div>

<div class="card"><h2>Upload history</h2>
    @if($project->uploads->isEmpty())<p class="muted">No successful uploads yet.</p>
    @else<div class="table-wrap"><table><thead><tr><th>ZIP</th><th>Files</th><th>Extracted size</th><th>Uploaded</th></tr></thead><tbody>@foreach($project->uploads as $upload)<tr><td>{{ $upload->original_name }}</td><td>{{ number_format($upload->file_count) }}</td><td>{{ number_format($upload->extracted_size_bytes / 1048576, 2) }} MB</td><td>{{ $upload->created_at->format('M j, Y g:i A') }}</td></tr>@endforeach</tbody></table></div>@endif
</div>
@endsection
