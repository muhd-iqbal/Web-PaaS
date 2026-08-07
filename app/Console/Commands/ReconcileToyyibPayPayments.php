<?php

namespace App\Console\Commands;

use App\Services\BillingManager;
use Illuminate\Console\Command;

class ReconcileToyyibPayPayments extends Command
{
    protected $signature = 'billing:reconcile-toyyibpay';

    protected $description = 'Confirm pending ToyyibPay payments using the transaction API';

    public function handle(BillingManager $billing): int
    {
        $count = $billing->reconcilePendingPayments();
        $this->info("Confirmed {$count} pending ToyyibPay payment(s).");

        return self::SUCCESS;
    }
}
