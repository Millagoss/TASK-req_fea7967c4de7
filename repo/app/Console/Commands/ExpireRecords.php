<?php

namespace App\Console\Commands;

use App\Models\DisciplinaryRecord;
use Illuminate\Console\Command;

class ExpireRecords extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'records:expire';

    /**
     * The console command description.
     */
    protected $description = 'Auto-clear expired active disciplinary records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = DisciplinaryRecord::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'status'         => 'cleared',
                'cleared_at'     => now(),
                'cleared_reason' => 'Auto-expired',
            ]);

        $this->info("Expired {$count} disciplinary record(s).");

        return self::SUCCESS;
    }
}
