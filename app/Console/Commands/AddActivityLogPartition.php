<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AddActivityLogPartition extends Command
{
    protected $signature = 'activity:partition {months=1 : add this many monthly partitions}';
    protected $description = 'Add monthly partitions for activity_logs table';

    public function handle()
    {
        $months = (int) $this->argument('months');
        $table = 'activity_logs';

        // Verify table exists
        $exists = DB::selectOne("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$table]);
        if (!$exists || $exists->c == 0) {
            $this->error("Table {$table} does not exist in database; cannot add partitions.");
            return 1;
        }

        // Get last partition max boundary via SHOW CREATE TABLE, parse last partition boundary OR compute next from now.
        // For simplicity we compute from now (start of current month) and create partitions for next N months.
        $now = Carbon::now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $part_start = $now->copy()->addMonths($i);
            $part_name  = 'p' . $part_start->format('Ym');
            $boundary   = $part_start->copy()->addMonth()->format('Y-m-d');

            // Build ALTER TABLE: add partition before p_max. We must DROP p_max, add new partitions, then re-add p_max.
            // Simpler: try to add partition directly (MySQL 5.7 supports ADD PARTITION).
            $sql = "ALTER TABLE `{$table}` ADD PARTITION (PARTITION `{$part_name}` VALUES LESS THAN (TO_DAYS('{$boundary}')))";
            try {
                DB::unprepared($sql);
                $this->info("Added partition {$part_name} (less than {$boundary})");
            } catch (\Throwable $e) {
                $this->error("Failed to add partition {$part_name}: " . $e->getMessage());
            }
        }

        $this->info('Done.');
        return 0;
    }
}
