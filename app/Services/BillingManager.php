<?php

namespace App\Services;

use App\Contracts\BillingGateway;
use App\Enums\ProjectStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BillingException;
use App\Models\BillingWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class BillingManager
{
    public function __construct(
        private readonly BillingGateway $gateway,
        private readonly DeploymentManager $deployments,
    ) {}

    public function checkoutUrl(User $user, Plan $plan): string
    {
        if (! $plan->is_active || $plan->isFree() || ! $plan->stripe_price_id) {
            throw new BillingException('This paid plan is not configured for checkout.');
        }

        $stripeSubscription = $user->subscriptions()->where('provider', 'stripe')->latest('id')->first();

        if ($stripeSubscription?->grantsAccess()) {
            throw new BillingException('Use the billing portal to change an existing paid subscription.');
        }

        return $this->gateway->checkoutUrl(
            $user,
            $plan,
            route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
            route('billing.index'),
        );
    }

    public function portalUrl(User $user): string
    {
        return $this->gateway->portalUrl($user, route('billing.index'));
    }

    public function activateFreePlan(User $user, Plan $plan): Subscription
    {
        if (! $plan->is_active || ! $plan->isFree()) {
            throw new BillingException('This plan cannot be activated without payment.');
        }

        if ($user->subscriptions()->whereIn('provider', ['internal', 'legacy'])
            ->whereHas('plan', fn ($query) => $query->where('monthly_price', '<=', 0))
            ->exists()) {
            throw new BillingException('The free trial has already been used on this account.');
        }

        $subscription = $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'provider' => 'internal',
            'status' => $plan->trial_days > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
        ]);
        $this->syncEntitlement($user);

        return $subscription;
    }

    /** @param array<string, mixed> $event */
    public function handleWebhook(array $event): void
    {
        $eventId = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if ($eventId === '' || $type === '') {
            throw new BillingException('The Stripe webhook payload is incomplete.');
        }

        Cache::lock('billing-webhook:'.hash('sha256', $eventId), 60)->block(10, function () use ($event, $eventId, $type): void {
            $record = BillingWebhookEvent::query()->firstOrCreate(
                ['provider_event_id' => $eventId],
                ['type' => $type],
            );

            if ($record->processed_at) {
                return;
            }

            $object = $event['data']['object'] ?? [];

            if ($type === 'checkout.session.completed') {
                $this->handleCheckoutCompleted($object, $event['created'] ?? null);
            } elseif (in_array($type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
                $this->syncStripeSubscription($object, $event['created'] ?? null);
            }

            $record->update(['processed_at' => now()]);
        });
    }

    public function expireInternalTrials(): int
    {
        $expired = Subscription::query()
            ->where('provider', 'internal')
            ->where('status', SubscriptionStatus::Trialing)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => SubscriptionStatus::Canceled, 'ended_at' => now()]);
            $this->syncEntitlement($subscription->user);
        }

        return $expired->count();
    }

    public function enforceAllEntitlements(): int
    {
        $count = 0;
        User::query()
            ->where(fn ($query) => $query->whereHas('subscriptions')->orWhereHas('projects'))
            ->eachById(function (User $user) use (&$count): void {
                $this->syncEntitlement($user);
                $count++;
            });

        return $count;
    }

    /** @param array<string, mixed> $session */
    private function handleCheckoutCompleted(array $session, mixed $eventCreated): void
    {
        $userId = $session['client_reference_id'] ?? $session['metadata']['user_id'] ?? null;
        $customerId = $this->stripeId($session['customer'] ?? null);
        $subscriptionId = $this->stripeId($session['subscription'] ?? null);
        $user = User::query()->find($userId);

        if (! $user || ! $customerId || ! $subscriptionId) {
            throw new BillingException('The completed checkout could not be matched to an account.');
        }

        $user->update(['stripe_customer_id' => $customerId]);
        $this->syncStripeSubscription($this->gateway->retrieveSubscription($subscriptionId), $eventCreated);
    }

    /** @param array<string, mixed> $data */
    private function syncStripeSubscription(array $data, mixed $eventCreated): void
    {
        $subscriptionId = (string) ($data['id'] ?? '');
        $customerId = $this->stripeId($data['customer'] ?? null);
        $priceId = $this->stripeId($data['items']['data'][0]['price']['id'] ?? null);
        $userId = $data['metadata']['user_id'] ?? null;
        $existing = Subscription::query()->where('provider_subscription_id', $subscriptionId)->with(['user', 'plan'])->first();
        $user = User::query()->where('stripe_customer_id', $customerId)->first() ?: User::query()->find($userId) ?: $existing?->user;
        $plan = Plan::query()->where('stripe_price_id', $priceId)->first();

        if (! $plan && $existing?->provider_price_id === $priceId) {
            $plan = $existing->plan;
        }

        $status = SubscriptionStatus::tryFrom((string) ($data['status'] ?? ''));
        $eventCreatedAt = $this->timestamp($eventCreated);
        $planId = $plan?->id ?? $existing?->plan_id;

        if (! $user || ! $status || $subscriptionId === '' || ! $customerId || (! $planId && $status->grantsAccess())) {
            throw new BillingException('The Stripe subscription could not be matched to an account and plan.');
        }

        DB::transaction(function () use ($data, $subscriptionId, $customerId, $priceId, $user, $planId, $status, $eventCreatedAt): void {
            $user->update(['stripe_customer_id' => $customerId]);
            $subscription = Subscription::query()->where('provider_subscription_id', $subscriptionId)->lockForUpdate()->first();

            if ($subscription?->provider_event_created_at && (! $eventCreatedAt || $subscription->provider_event_created_at->isAfter($eventCreatedAt))) {
                return;
            }

            if ($subscription?->provider_event_created_at?->equalTo($eventCreatedAt)
                && ! $subscription->status->grantsAccess()
                && $status->grantsAccess()) {
                return;
            }

            $subscription ??= new Subscription(['provider_subscription_id' => $subscriptionId]);
            $subscription->fill([
                'user_id' => $user->id,
                'plan_id' => $planId,
                'provider' => 'stripe',
                'provider_price_id' => $priceId,
                'status' => $status,
                'provider_event_created_at' => $eventCreatedAt,
                'current_period_start' => $this->timestamp($data['current_period_start'] ?? $data['items']['data'][0]['current_period_start'] ?? null),
                'current_period_end' => $this->timestamp($data['current_period_end'] ?? $data['items']['data'][0]['current_period_end'] ?? null),
                'cancel_at_period_end' => (bool) ($data['cancel_at_period_end'] ?? false),
                'ended_at' => $this->timestamp($data['ended_at'] ?? null),
            ])->save();

            if ($subscription->grantsAccess()) {
                $user->subscriptions()
                    ->whereKeyNot($subscription->id)
                    ->whereIn('provider', ['internal', 'legacy'])
                    ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value, SubscriptionStatus::PastDue->value])
                    ->update(['status' => SubscriptionStatus::Canceled->value, 'ended_at' => now()]);
            }
        });

        $this->syncEntitlement($user->refresh());
    }

    private function syncEntitlement(User $user): void
    {
        $subscription = $user->currentSubscription();
        $user->update(['plan_id' => $subscription?->plan_id]);
        $websiteLimit = $subscription?->plan?->website_limit ?? 0;
        $projects = $user->projects()->oldest('id')->get();

        foreach ($projects->slice($websiteLimit) as $project) {
            if (! $project->container_name || in_array($project->status, [ProjectStatus::Deploying, ProjectStatus::Suspended], true)) {
                continue;
            }

            try {
                $this->deployments->queueSuspend($project, $user);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function stripeId(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_array($value) && isset($value['id']) ? (string) $value['id'] : null;
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        return is_numeric($value) ? CarbonImmutable::createFromTimestampUTC((int) $value) : null;
    }
}
