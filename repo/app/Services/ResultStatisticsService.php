<?php

namespace App\Services;

use App\Models\MeasurementCode;
use App\Models\Result;
use App\Models\ResultStatistic;

class ResultStatisticsService
{
    /**
     * Recompute statistics for all active measurement codes.
     *
     * @return int Number of codes updated
     */
    public function recomputeAll(): int
    {
        $codes = MeasurementCode::where('is_active', true)->get();
        $count = 0;

        foreach ($codes as $code) {
            $this->computeForCode($code->id);
            $count++;
        }

        return $count;
    }

    /**
     * Compute mean/stddev for a single measurement code from approved numeric results.
     */
    public function computeForCode(int $codeId): ResultStatistic
    {
        $stats = Result::where('measurement_code_id', $codeId)
            ->where('review_status', 'approved')
            ->whereNotNull('value_numeric')
            ->selectRaw('COUNT(*) as cnt, AVG(value_numeric) as avg_val, STDDEV(value_numeric) as std_val')
            ->first();

        return ResultStatistic::updateOrCreate(
            ['measurement_code_id' => $codeId],
            [
                'count'            => $stats->cnt ?? 0,
                'mean'             => $stats->avg_val,
                'stddev'           => $stats->std_val,
                'last_computed_at' => now(),
            ]
        );
    }
}
