<?php

namespace App\Contracts;

use App\Models\Plan;
use App\Models\User;

interface BillingGateway
{
    public function checkoutUrl(User $user, Plan $plan, string $successUrl, string $cancelUrl): string;

    public function portalUrl(User $user, string $returnUrl): string;

    /** @return array<string, mixed> */
    public function parseWebhook(string $payload, string $signature): array;

    /** @return array<string, mixed> */
    public function retrieveSubscription(string $subscriptionId): array;
}
