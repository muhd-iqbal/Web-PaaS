@extends('layouts.app')
@section('title', 'Billing')
@section('content')
<div class="page-head"><div><div class="eyebrow">Account billing</div><h1>Prepaid hosting</h1><p class="muted">Make a secure one-off payment through ToyyibPay. There is no automatic renewal.</p></div></div>
@if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
@error('billing')<div class="alert" style="border-color:#fecaca;background:#fef2f2;color:#b91c1c">{{ $message }}</div>@enderror

<div class="card" style="margin-bottom:28px">
    <div class="muted small">Current access</div>
    <div class="stat">{{ $currentSubscription?->plan?->name ?? 'No active plan' }}</div>
    @if($currentSubscription)
        <p class="muted">{{ $currentSubscription->status->getLabel() }} @if($currentSubscription->current_period_end) · Access ends {{ $currentSubscription->current_period_end->format('M j, Y, g:i A') }} @endif</p>
    @else
        <p class="muted">Website changes and deployments require active hosting access.</p>
    @endif
</div>

<div class="grid" style="margin-bottom:28px">
@foreach($plans as $plan)
    <article class="card"><h2>{{ $plan->name }}</h2><div class="price">{{ $plan->formattedPrice() }}<span class="muted small"> / {{ $plan->isFree() ? $plan->trial_days : $plan->access_days }} days</span></div><p class="muted">{{ $plan->description }}</p>
        <ul class="clean"><li>{{ $plan->website_limit }} {{ Str::plural('website', $plan->website_limit) }}</li><li>{{ number_format($plan->storage_mb / 1024, 1) }} GB storage</li><li>{{ number_format($plan->bandwidth_mb / 1024, 0) }} GB bandwidth</li><li>{{ $plan->database_mb ? $plan->database_mb.' MB database allowance' : 'No database' }}</li>@if($plan->trial_days)<li>{{ $plan->trial_days }}-day free trial</li>@endif</ul>
        @if($plan->isFree() && $currentSubscription?->plan_id === $plan->id)
            <span class="badge">Current plan</span>
        @else
            <form method="POST" action="{{ route('billing.subscribe', $plan) }}">@csrf<button class="button" type="submit">{{ $plan->isFree() ? 'Start free trial' : ($currentSubscription?->plan_id === $plan->id ? 'Extend access' : 'Pay with ToyyibPay') }}</button></form>
        @endif
    </article>
@endforeach
</div>

@if($payments->isNotEmpty())<div class="card" style="margin-bottom:28px"><h2>Payment history</h2><div class="table-wrap"><table><thead><tr><th>Plan</th><th>Amount</th><th>Status</th><th>Reference</th><th>Date</th></tr></thead><tbody>@foreach($payments as $payment)<tr><td>{{ $payment->plan?->name ?? 'Archived plan' }}</td><td>RM {{ number_format((float) $payment->amount, 2) }}</td><td><span class="badge">{{ $payment->status->getLabel() }}</span></td><td>{{ $payment->provider_transaction_id ?? $payment->external_reference }}</td><td>{{ $payment->created_at->format('M j, Y') }}</td></tr>@endforeach</tbody></table></div></div>@endif

@if($subscriptions->isNotEmpty())<div class="card"><h2>Access history</h2><div class="table-wrap"><table><thead><tr><th>Plan</th><th>Source</th><th>Status</th><th>Access end</th></tr></thead><tbody>@foreach($subscriptions as $subscription)<tr><td>{{ $subscription->plan?->name ?? 'Archived plan' }}</td><td>{{ $subscription->provider === 'toyyibpay' ? 'ToyyibPay' : ucfirst($subscription->provider) }}</td><td><span class="badge">{{ $subscription->status->getLabel() }}</span></td><td>{{ $subscription->current_period_end?->format('M j, Y') ?? '—' }}</td></tr>@endforeach</tbody></table></div></div>@endif
@endsection
