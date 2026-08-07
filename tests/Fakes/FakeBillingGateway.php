<?php

namespace Tests\Fakes;

use App\Contracts\BillingGateway;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;

class FakeBillingGateway implements BillingGateway
{
    public string $checkout = 'https://dev.toyyibpay.test/bill-code';

    public bool $authentic = true;

    public bool $successful = true;

    /** @var list<array{user_id: int, plan_id: int, payment_id: int}> */
    public array $checkouts = [];

    public function checkoutUrl(User $user, Plan $plan, Payment $payment, string $returnUrl, string $callbackUrl): string
    {
        $payment->update(['provider_bill_code' => 'bill_'.$payment->id]);
        $this->checkouts[] = [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'payment_id' => $payment->id,
        ];

        return $this->checkout;
    }

    public function callbackIsAuthentic(array $payload): bool
    {
        return $this->authentic;
    }

    public function successfulTransaction(Payment $payment, ?string $expectedReference = null): ?string
    {
        return $this->successful ? ($expectedReference ?? 'TXN-RECONCILED') : null;
    }
}
