<?php

namespace App\Console\Commands;

use App\Services\BillingManager;
use Illuminate\Console\Command;

class ExpireInternalTrials extends Command
{
    protected $signature = 'subscriptions:expire-trials';

    protected $description = 'Expire time-limited hosting access and enforce account entitlements';

    public function handle(BillingManager $billing): int
    {
        $count = $billing->expireInternalTrials();
        $accounts = $billing->enforceAllEntitlements();
        $this->info("Expired {$count} hosting access period(s).");
        $this->info("Enforced entitlements for {$accounts} account(s).");

        return self::SUCCESS;
    }
}
