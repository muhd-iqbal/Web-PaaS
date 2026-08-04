<?php

namespace App\Console\Commands;

use App\Services\BillingManager;
use Illuminate\Console\Command;

class ExpireInternalTrials extends Command
{
    protected $signature = 'subscriptions:expire-trials';

    protected $description = 'Expire internal free trials and enforce hosting access';

    public function handle(BillingManager $billing): int
    {
        $count = $billing->expireInternalTrials();
        $accounts = $billing->enforceAllEntitlements();
        $this->info("Expired {$count} internal free trial(s).");
        $this->info("Enforced entitlements for {$accounts} account(s).");

        return self::SUCCESS;
    }
}
