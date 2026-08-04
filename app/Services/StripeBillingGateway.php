<?php

namespace App\Services;

use App\Contracts\BillingGateway;
use App\Exceptions\BillingException;
use App\Models\Plan;
use App\Models\User;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;

class StripeBillingGateway implements BillingGateway
{
    public function checkoutUrl(User $user, Plan $plan, string $successUrl, string $cancelUrl): string
    {
        try {
            $parameters = [
                'mode' => 'subscription',
                'line_items' => [['price' => $plan->stripe_price_id, 'quantity' => 1]],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $user->id,
                'metadata' => ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id],
                'subscription_data' => ['metadata' => ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id]],
                'allow_promotion_codes' => true,
            ];

            if ($user->stripe_customer_id) {
                $parameters['customer'] = $user->stripe_customer_id;
            } else {
                $parameters['customer_email'] = $user->email;
            }

            $session = $this->client()->checkout->sessions->create($parameters, [
                'idempotency_key' => "checkout-{$user->id}-{$plan->id}-".intdiv(time(), 1800),
            ]);

            if (! $session->url) {
                throw new BillingException('Stripe did not return a checkout address.');
            }

            return $session->url;
        } catch (BillingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new BillingException('Secure checkout is temporarily unavailable.', previous: $exception);
        }
    }

    public function portalUrl(User $user, string $returnUrl): string
    {
        if (! $user->stripe_customer_id) {
            throw new BillingException('This account does not have Stripe billing details yet.');
        }

        try {
            $session = $this->client()->billingPortal->sessions->create([
                'customer' => $user->stripe_customer_id,
                'return_url' => $returnUrl,
            ]);

            return $session->url;
        } catch (Throwable $exception) {
            throw new BillingException('The billing portal is temporarily unavailable.', previous: $exception);
        }
    }

    public function parseWebhook(string $payload, string $signature): array
    {
        try {
            return Webhook::constructEvent($payload, $signature, config('services.stripe.webhook_secret'))->toArray();
        } catch (Throwable $exception) {
            throw new BillingException('The Stripe webhook signature is invalid.', previous: $exception);
        }
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        try {
            return $this->client()->subscriptions->retrieve($subscriptionId, [])->toArray();
        } catch (Throwable $exception) {
            throw new BillingException('Stripe subscription details could not be retrieved.', previous: $exception);
        }
    }

    private function client(): StripeClient
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new BillingException('Stripe billing is not configured.');
        }

        return new StripeClient($secret);
    }
}
