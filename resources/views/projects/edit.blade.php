@extends('layouts.app')
@section('title', 'Edit '.$project->name)
@section('content')
<div class="card form-card"><div class="eyebrow">Project settings</div><h2>Edit {{ $project->name }}</h2>
    <form method="POST" action="{{ route('projects.update', $project) }}">@csrf @method('PUT') @include('projects._form', ['submitLabel' => 'Save changes'])</form>
</div>
@endsection
