@extends('layouts.app')
@section('title', 'Create website')
@section('content')
<div class="card form-card"><div class="eyebrow">New project</div><h2>Create a website</h2><p class="muted">Set up its name and runtime, then upload the website as a ZIP.</p>
    <form method="POST" action="{{ route('projects.store') }}">@csrf @include('projects._form', ['submitLabel' => 'Create website'])</form>
</div>
@endsection
