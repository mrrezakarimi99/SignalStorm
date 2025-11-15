<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicatePredictionsCommand extends Command
{
    protected $signature = 'predict:cleanup-duplicates
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--days=7 : Clean up duplicates from last X days}';

    protected $description = 'Remove duplicate predictions (keeps only the latest per symbol+interval)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $days = (int) $this->option('days');

        $this->info("🔍 Searching for duplicate predictions...");
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No data will be deleted");
        }

        // Find duplicates: multiple predictions with same symbol+interval created within 5 minutes
        $duplicates = DB::table('predictions')
            ->select('symbol', 'interval', DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD HH24:MI') as time_group"))
            ->selectRaw('COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('symbol', 'interval', 'time_group')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info("✓ No duplicates found!");
            return self::SUCCESS;
        }

        $this->info("Found {$duplicates->count()} groups with duplicates:");

        $totalDeleted = 0;

        foreach ($duplicates as $duplicate) {
            // Get all predictions in this group
            $predictions = Prediction::where('symbol', $duplicate->symbol)
                ->where('interval', $duplicate->interval)
                ->whereBetween('created_at', [
                    \Carbon\Carbon::parse($duplicate->time_group),
                    \Carbon\Carbon::parse($duplicate->time_group)->addMinutes(1)
                ])
                ->orderBy('created_at', 'desc')
                ->orderBy('confidence', 'desc')
                ->get();

            // Keep the first one (latest + highest confidence), delete the rest
            $toKeep = $predictions->first();
            $toDelete = $predictions->slice(1);

            if ($toDelete->isEmpty()) {
                continue;
            }

            $this->line("\n📊 {$duplicate->symbol} {$duplicate->interval} at {$duplicate->time_group}:");
            $this->line("   Total: {$duplicate->count} | Keeping: 1 (ID: {$toKeep->id}) | Deleting: {$toDelete->count()}");

            if (!$dryRun) {
                $ids = $toDelete->pluck('id')->toArray();
                $deleted = Prediction::whereIn('id', $ids)->delete();
                $totalDeleted += $deleted;
                $this->info("   ✓ Deleted {$deleted} duplicates");
            } else {
                $this->comment("   → Would delete IDs: " . $toDelete->pluck('id')->implode(', '));
            }
        }

        if ($dryRun) {
            $this->warn("\n⚠️  DRY RUN - Would have deleted {$toDelete->count()} total predictions");
            $this->info("Run without --dry-run to actually delete duplicates");
        } else {
            $this->info("\n✓ Cleanup complete! Deleted {$totalDeleted} duplicate predictions");
        }

        return self::SUCCESS;
    }
}

