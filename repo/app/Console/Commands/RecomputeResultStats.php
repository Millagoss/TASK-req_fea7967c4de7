<?php

namespace App\Console\Commands;

use App\Services\ResultStatisticsService;
use Illuminate\Console\Command;

class RecomputeResultStats extends Command
{
    protected $signature = 'results:recompute-stats';
    protected $description = 'Recompute result statistics for all measurement codes';

    public function handle(ResultStatisticsService $service): int
    {
        $count = $service->recomputeAll();
        $this->info("Recomputed statistics for {$count} measurement codes.");
        return self::SUCCESS;
    }
}
