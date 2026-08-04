@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<div class="card form-card"><div class="eyebrow">Welcome back</div><h2>Log in to your dashboard</h2>
    <form method="POST" action="{{ route('login') }}">@csrf
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">@error('email')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password">@error('password')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label style="font-weight:400"><input style="width:auto;min-height:auto" name="remember" type="checkbox"> Remember me</label></div>
        <button class="button" type="submit">Log in</button>
    </form>
    <p class="muted small">New here? <a href="{{ route('register') }}">Create an account</a>.</p>
</div>
@endsection
