<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;

interface BillingGateway
{
    public function checkoutUrl(User $user, Plan $plan, Payment $payment, string $returnUrl, string $callbackUrl): string;

    /** @param array<string, mixed> $payload */
    public function callbackIsAuthentic(array $payload): bool;

    /** @param array<string, mixed> $payload */
    public function paymentIsSuccessful(Payment $payment, array $payload): bool;
}
