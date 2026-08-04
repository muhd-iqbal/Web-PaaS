<?php

namespace Tests\Fakes;

use App\Contracts\BillingGateway;
use App\Exceptions\BillingException;
use App\Models\Plan;
use App\Models\User;

class FakeBillingGateway implements BillingGateway
{
    public string $checkout = 'https://checkout.stripe.test/session';

    public string $portal = 'https://billing.stripe.test/portal';

    /** @var array<string, mixed> */
    public array $webhook = [];

    /** @var array<string, array<string, mixed>> */
    public array $subscriptions = [];

    public bool $invalidSignature = false;

    /** @var list<array{user_id: int, plan_id: int}> */
    public array $checkouts = [];

    public function checkoutUrl(User $user, Plan $plan, string $successUrl, string $cancelUrl): string
    {
        $this->checkouts[] = ['user_id' => $user->id, 'plan_id' => $plan->id];

        return $this->checkout;
    }

    public function portalUrl(User $user, string $returnUrl): string
    {
        return $this->portal;
    }

    public function parseWebhook(string $payload, string $signature): array
    {
        if ($this->invalidSignature) {
            throw new BillingException('Invalid signature');
        }

        return $this->webhook;
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->subscriptions[$subscriptionId] ?? throw new BillingException('Unknown subscription');
    }
}
