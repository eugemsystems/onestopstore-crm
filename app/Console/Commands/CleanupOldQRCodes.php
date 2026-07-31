<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OrderQRCodeService;

/**
 * Clean up old QR code files from storage (CRM)
 * This command can be scheduled to run periodically
 */
class CleanupOldQRCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qrcodes:cleanup {--days=30 : Delete QR codes older than this many days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old order QR code files from storage';

    /**
     * Execute the console command.
     */
    public function handle(OrderQRCodeService $qrCodeService): int
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up QR codes older than {$days} days...");

        $deletedCount = $qrCodeService->cleanupOldFiles($days);

        if ($deletedCount > 0) {
            $this->info("✓ Deleted {$deletedCount} old QR code file(s)");
        } else {
            $this->info("✓ No old QR codes found to delete");
        }

        return Command::SUCCESS;
    }
}
