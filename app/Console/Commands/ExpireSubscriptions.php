<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StudentSubscription;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Expire subscriptions that have passed their expiry date';

    public function handle()
    {
        $count = StudentSubscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} subscriptions");
    }
}