<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FraudLog;

class ExportFraudLogs extends Command
{
    protected $signature = 'fraud:export-logs';
    protected $description = 'Export fraud logs for ML retraining';

    public function handle()
    {
        $path = storage_path('app/fraud_logs.json');
        $logs = FraudLog::latest()->take(10000)->get()->toArray();
        file_put_contents($path, json_encode($logs));
        $this->info("✅ Exported logs to $path");
    }
}
