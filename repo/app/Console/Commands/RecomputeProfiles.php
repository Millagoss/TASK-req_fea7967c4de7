<?php

namespace App\Console\Commands;

use App\Services\ProfileComputationService;
use Illuminate\Console\Command;

class RecomputeProfiles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'profiles:recompute';

    /**
     * The console command description.
     */
    protected $description = 'Recompute all user behavior profiles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = new ProfileComputationService();
        $count   = $service->computeAll();

        $this->info("Recomputed {$count} user profile(s).");

        return self::SUCCESS;
    }
}
