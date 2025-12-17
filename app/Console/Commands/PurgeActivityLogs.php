<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;
use Carbon\Carbon;

class PurgeActivityLogs extends Command
{
    /**
     * Signature:
     *  --days    : delete logs older than this many days (default 365)
     *  --batch   : number of rows to delete per DB operation (default 1000)
     *  --dry-run : do not actually delete, only report counts
     *  --max     : maximum rows to delete in this run (0 = unlimited)
     *  --force   : skip confirmation prompt in interactive consoles
     */
    protected $signature = 'activity:purge
                            {--days=365 : Delete logs older than this many days}
                            {--batch=1000 : Number of rows to delete per batch}
                            {--dry-run : Do not delete, only show what would be deleted}
                            {--max=0 : Maximum number of rows to delete in this run (0 = unlimited)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Purge old activity logs beyond configured retention period (batched & safe).';

    public function handle(): int
    {
        $days  = (int) $this->option('days');
        $batch = (int) $this->option('batch');
        $dry   = (bool) $this->option('dry-run');
        $max   = (int) $this->option('max');
        $force = (bool) $this->option('force');

        if ($days <= 0) {
            $this->error('Invalid --days value. Must be > 0.');
            return 1;
        }

        if ($batch <= 0 || $batch > 50000) {
            $this->error('Invalid --batch value. Use a positive number (recommend 500..5000).');
            return 1;
        }

        $cutoff = Carbon::now()->subDays($days);

        $this->info("PurgeActivityLogs: deleting ActivityLog rows older than {$cutoff->toDateTimeString()} (days={$days})");
        $this->info('Options: batch=' . $batch . ' dry-run=' . ($dry ? 'yes' : 'no') . ' max=' . ($max === 0 ? 'unlimited' : $max));

        if (! $force) {
            if (! $this->confirm('Proceed?')) {
                $this->info('Aborted by user.');
                return 0;
            }
        }

        // Count total rows to be deleted (fast count)
        $totalToDelete = ActivityLog::where('created_at', '<', $cutoff)->count();

        $this->info("Total candidate rows: {$totalToDelete}");

        if ($totalToDelete === 0) {
            $this->info('Nothing to delete.');
            return 0;
        }

        if ($dry) {
            $this->info('Dry-run mode: no rows will be deleted.');
            return 0;
        }

        $deleted = 0;
        $start = microtime(true);

        // Delete in batches. Use ID-based batching to avoid skipping / locking.
        // We loop until we hit totalToDelete or max (if provided).
        while (true) {

            // Respect --max
            if ($max > 0 && $deleted >= $max) {
                break;
            }

            // Pull a page of IDs to delete
            $ids = ActivityLog::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id')
                ->toArray();

            if (empty($ids)) {
                break;
            }

            // Adjust to not exceed --max
            if ($max > 0 && ($deleted + count($ids)) > $max) {
                $ids = array_slice($ids, 0, max(0, $max - $deleted));
            }

            try {
                // Use a short transaction per batch to keep things safe
                DB::transaction(function () use ($ids) {
                    // Delete by id set (fast, uses index)
                    ActivityLog::whereIn('id', $ids)->delete();
                });

                $deleted += count($ids);

                $this->output->write("\rDeleted: {$deleted} / {$totalToDelete}");

                // small pause to reduce DB pressure if needed (commented by default)
                // usleep(100000); // 0.1s

            } catch (\Throwable $e) {
                // Log error, return non-zero
                Log::error('PurgeActivityLogs batch delete failed', [
                    'error' => $e->getMessage(),
                    'ids_sample' => array_slice($ids, 0, 5),
                ]);
                $this->error("\nBatch delete failed: " . $e->getMessage());
                return 2;
            }
        }

        $time = round(microtime(true) - $start, 2);
        $this->info("\nFinished. Deleted {$deleted} rows in {$time} seconds.");

        Log::info('PurgeActivityLogs completed', [
            'deleted' => $deleted,
            'cutoff'  => $cutoff->toDateTimeString(),
            'batch'   => $batch,
            'max'     => $max,
        ]);

        return 0;
    }
}
