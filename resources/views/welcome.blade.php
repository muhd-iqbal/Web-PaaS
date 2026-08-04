@extends('layouts.app')
@section('title', 'Simple hosting for your next project')
@section('content')
    <section class="hero">
        <div class="eyebrow">Hosting without the server work</div>
        <h1>Put your website online in a few simple steps.</h1>
        <p>Create an account and a website project now. ZIP upload and one-click deployment arrive in the next phases.</p>
        <div class="actions"><a class="button" href="{{ route('register') }}">Choose a plan</a><a class="button secondary" href="{{ route('login') }}">Log in</a></div>
    </section>
    <section style="margin-top:48px"><div class="eyebrow">Plans</div><h2>Start small and grow when you need to.</h2>
        <div class="grid">
            @forelse($plans as $plan)
                <article class="card"><h3>{{ $plan->name }}</h3><div class="price">${{ number_format((float) $plan->monthly_price, 2) }}<span class="muted small"> / month</span></div><p class="muted">{{ $plan->description }}</p>
                    <ul class="clean"><li>{{ $plan->website_limit }} {{ Str::plural('website', $plan->website_limit) }}</li><li>{{ number_format($plan->storage_mb / 1024, 1) }} GB storage</li><li>{{ number_format($plan->bandwidth_mb / 1024, 0) }} GB bandwidth</li><li>{{ $plan->database_mb ? $plan->database_mb.' MB database' : 'No database' }}</li></ul>
                </article>
            @empty
                <div class="card"><p>Plans will appear after the database seeder is run.</p></div>
            @endforelse
        </div>
    </section>
@endsection
