<?php

namespace App\Console\Commands;

use App\Jobs\ReconcilePayoutsJob;
use Illuminate\Console\Command;

class ReconcilePayoutsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payouts:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile payout statuses with payment provider';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting payout reconciliation...');
        
        // Dispatch the job
        ReconcilePayoutsJob::dispatch();
        
        $this->info('Payout reconciliation job dispatched successfully.');
        
        return Command::SUCCESS;
    }
}
