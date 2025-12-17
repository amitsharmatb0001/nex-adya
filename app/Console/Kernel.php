<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // Reconcile payouts every 15 minutes
        $schedule->job(new \App\Jobs\ReconcilePayoutsJob())->everyFifteenMinutes();
        
        // Run payout reconciliation command hourly without overlapping
        $schedule->command('payouts:reconcile')->hourly()->withoutOverlapping();
        
        // Purge old activity logs daily at 2 AM
        $schedule->command('activity:purge --days=365')->dailyAt('02:00');
        
        // Export fraud logs daily at 3 AM
        $schedule->command('fraud:export')->dailyAt('03:00');
    }


    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    
}
