@extends('layouts.app')
@section('title', 'Feedback')
@section('content')
<div class="page-head"><div><div class="eyebrow">Help improve the service</div><h1>Feedback and suggestions</h1><p class="muted">Tell us what worked, what failed, or what would make the service clearer. Suggestions are reviewed but are not a promise of future development.</p></div></div>

<div class="grid two" style="align-items:start">
    <section class="card">
        <h2>Send feedback</h2>
        <form method="POST" action="{{ route('feedback.store') }}">@csrf
            <div class="field"><label for="type">Type</label><select id="type" name="type" required>@foreach($types as $type)<option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->getLabel() }}</option>@endforeach</select>@error('type')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" maxlength="150" value="{{ old('subject') }}" required>@error('subject')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="message">Message</label><textarea id="message" name="message" minlength="10" maxlength="5000" required>{{ old('message') }}</textarea><div class="muted small">Please do not include passwords, secret keys, payment details, or sensitive personal data.</div>@error('message')<div class="error">{{ $message }}</div>@enderror</div>
            <button class="button" type="submit">Submit feedback</button>
        </form>
    </section>

    <section class="card">
        <h2>Your submissions</h2>
        @forelse($submissions as $submission)
            <div style="padding:14px 0;border-bottom:1px solid var(--line)"><div class="actions" style="justify-content:space-between"><strong>{{ $submission->subject }}</strong><span class="badge">{{ $submission->status->getLabel() }}</span></div><div class="muted small">{{ $submission->type->getLabel() }} · {{ $submission->created_at->format('M j, Y') }}</div><p style="white-space:pre-wrap">{{ $submission->message }}</p></div>
        @empty
            <p class="muted">You have not submitted feedback yet.</p>
        @endforelse
        {{ $submissions->links() }}
    </section>
</div>
@endsection
