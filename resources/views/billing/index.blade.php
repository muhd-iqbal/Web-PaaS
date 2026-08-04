@extends('layouts.app')
@section('title', 'Billing')
@section('content')
<div class="page-head"><div><div class="eyebrow">Account billing</div><h1>Hosting subscription</h1><p class="muted">Choose a plan or manage payment details securely through Stripe.</p></div>@if(auth()->user()->stripe_customer_id)<form method="POST" action="{{ route('billing.portal') }}">@csrf<button class="button secondary" type="submit">Open billing portal</button></form>@endif</div>
@error('billing')<div class="alert" style="border-color:#fecaca;background:#fef2f2;color:#b91c1c">{{ $message }}</div>@enderror

<div class="card" style="margin-bottom:28px">
    <div class="muted small">Current access</div>
    <div class="stat">{{ $currentSubscription?->plan?->name ?? 'No active plan' }}</div>
    @if($currentSubscription)
        <p class="muted">
            {{ $currentSubscription->status->getLabel() }}
            @if($currentSubscription->current_period_end)
                · Current period ends {{ $currentSubscription->current_period_end->format('M j, Y') }}
            @endif
            @if($currentSubscription->cancel_at_period_end)
                · Cancels at period end
            @endif
        </p>
    @else
        <p class="muted">Website changes and deployments require an active subscription.</p>
    @endif
</div>

<div class="grid" style="margin-bottom:28px">
@foreach($plans as $plan)
    <article class="card"><h2>{{ $plan->name }}</h2><div class="price">${{ number_format((float) $plan->monthly_price, 2) }}<span class="muted small"> / month</span></div><p class="muted">{{ $plan->description }}</p>
        <ul class="clean"><li>{{ $plan->website_limit }} {{ Str::plural('website', $plan->website_limit) }}</li><li>{{ number_format($plan->storage_mb / 1024, 1) }} GB storage</li><li>{{ number_format($plan->bandwidth_mb / 1024, 0) }} GB bandwidth</li><li>{{ $plan->database_mb ? $plan->database_mb.' MB database allowance' : 'No database' }}</li>@if($plan->trial_days)<li>{{ $plan->trial_days }}-day trial</li>@endif</ul>
        @if($currentSubscription?->plan_id === $plan->id)<span class="badge">Current plan</span>@elseif($currentSubscription?->provider === 'stripe')<p class="muted small">Use the billing portal to switch paid plans.</p>@else<form method="POST" action="{{ route('billing.subscribe', $plan) }}">@csrf<button class="button" type="submit">{{ $plan->isFree() ? 'Start free trial' : 'Continue to checkout' }}</button></form>@endif
    </article>
@endforeach
</div>

@if($subscriptions->isNotEmpty())<div class="card"><h2>Subscription history</h2><div class="table-wrap"><table><thead><tr><th>Plan</th><th>Provider</th><th>Status</th><th>Period end</th></tr></thead><tbody>@foreach($subscriptions as $subscription)<tr><td>{{ $subscription->plan?->name ?? 'Archived plan' }}</td><td>{{ ucfirst($subscription->provider) }}</td><td><span class="badge">{{ $subscription->status->getLabel() }}</span></td><td>{{ $subscription->current_period_end?->format('M j, Y') ?? '—' }}</td></tr>@endforeach</tbody></table></div></div>@endif
@endsection
