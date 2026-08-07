@extends('layouts.app')
@section('title', 'Create your account')
@section('content')
<div class="card form-card"><div class="eyebrow">Get started</div><h2>Create your hosting account</h2>
    <form method="POST" action="{{ route('register') }}">@csrf
        <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="plan_id">Hosting plan</label><select id="plan_id" name="plan_id" required><option value="">Choose a plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }} — {{ $plan->formattedPrice() }} / {{ $plan->isFree() ? $plan->trial_days : $plan->access_days }} days</option>@endforeach</select>@error('plan_id')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="new-password">@error('password')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
        <button class="button" type="submit">Create account</button>
    </form>
    <p class="muted small">Already registered? <a href="{{ route('login') }}">Log in</a>.</p>
</div>
@endsection
