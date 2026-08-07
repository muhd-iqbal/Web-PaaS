<?php

namespace App\Services;

use App\Contracts\BillingGateway;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BillingException;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class BillingManager
{
    public function __construct(
        private readonly BillingGateway $gateway,
        private readonly DeploymentManager $deployments,
    ) {}

    public function checkoutUrl(User $user, Plan $plan): string
    {
        if (! $plan->is_active || $plan->isFree() || $plan->access_days < 1) {
            throw new BillingException('This paid plan is not configured for checkout.');
        }

        $payment = $user->payments()->create([
            'plan_id' => $plan->id,
            'provider' => 'toyyibpay',
            'external_reference' => (string) Str::uuid(),
            'amount' => $plan->monthly_price,
            'currency' => $plan->currency,
            'status' => PaymentStatus::Pending,
        ]);

        try {
            return $this->gateway->checkoutUrl(
                $user,
                $plan,
                $payment,
                route('billing.return'),
                route('toyyibpay.callback'),
            );
        } catch (Throwable $exception) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'failure_reason' => Str::limit($exception->getMessage(), 255),
            ]);

            throw $exception;
        }
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

    /** @param array<string, mixed> $payload */
    public function handlePaymentCallback(array $payload): Payment
    {
        foreach (['status', 'order_id', 'billcode', 'refno', 'amount', 'hash'] as $field) {
            if (! isset($payload[$field]) || ! is_scalar($payload[$field])) {
                throw new BillingException('The ToyyibPay callback payload is incomplete.');
            }
        }

        if (! $this->gateway->callbackIsAuthentic($payload)) {
            throw new BillingException('The ToyyibPay callback signature is invalid.');
        }

        $payment = Payment::query()
            ->where('external_reference', (string) $payload['order_id'])
            ->first();

        if (! $payment || ! $payment->provider_bill_code
            || ! hash_equals($payment->provider_bill_code, (string) $payload['billcode'])) {
            throw new BillingException('The ToyyibPay payment could not be matched to a local bill.');
        }

        return Cache::lock('toyyibpay-payment:'.$payment->id, 60)->block(10, function () use ($payment, $payload): Payment {
            $payment->refresh();

            if ($payment->status === PaymentStatus::Successful) {
                return $payment;
            }

            $status = (string) $payload['status'];

            if ($status !== '1') {
                $payment->update([
                    'status' => $status === '2' ? PaymentStatus::Pending : PaymentStatus::Failed,
                    'provider_transaction_id' => (string) $payload['refno'],
                    'failure_reason' => $status === '3' ? Str::limit((string) ($payload['reason'] ?? 'Payment failed.'), 255) : null,
                ]);

                return $payment->refresh();
            }

            if (! $this->gateway->paymentIsSuccessful($payment, $payload)) {
                throw new BillingException('ToyyibPay could not verify the successful payment.');
            }

            $accessDays = $payment->plan?->access_days;

            if (! $accessDays || $accessDays < 1) {
                throw new BillingException('The plan for this payment is no longer available.');
            }

            $user = $payment->user;

            DB::transaction(function () use ($accessDays, $payment, $payload, $user): void {
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

                if ($lockedPayment->status === PaymentStatus::Successful) {
                    return;
                }

                $current = $user->currentSubscription();
                $periodStart = now();
                $extensionBase = $current?->current_period_end?->isFuture()
                    ? $current->current_period_end->copy()
                    : $periodStart->copy();

                $subscription = $user->subscriptions()->create([
                    'plan_id' => $lockedPayment->plan_id,
                    'provider' => 'toyyibpay',
                    'provider_subscription_id' => $lockedPayment->external_reference,
                    'status' => SubscriptionStatus::Active,
                    'current_period_start' => $periodStart,
                    'current_period_end' => $extensionBase->addDays($accessDays),
                ]);

                $user->subscriptions()
                    ->whereKeyNot($subscription->id)
                    ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value, SubscriptionStatus::PastDue->value])
                    ->update(['status' => SubscriptionStatus::Canceled->value, 'ended_at' => now()]);

                $lockedPayment->update([
                    'status' => PaymentStatus::Successful,
                    'provider_transaction_id' => (string) $payload['refno'],
                    'paid_at' => now(),
                    'failure_reason' => null,
                ]);
            });

            $this->syncEntitlement($user->refresh());

            return $payment->refresh();
        });
    }

    public function expireInternalTrials(): int
    {
        $expired = Subscription::query()
            ->whereIn('provider', ['internal', 'toyyibpay'])
            ->whereIn('status', [SubscriptionStatus::Trialing, SubscriptionStatus::Active])
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
}
