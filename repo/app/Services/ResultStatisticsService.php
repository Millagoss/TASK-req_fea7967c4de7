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
        $values = Result::where('measurement_code_id', $codeId)
            ->where('review_status', 'approved')
            ->whereNotNull('value_numeric')
            ->pluck('value_numeric')
            ->map(fn ($v) => (float) $v);

        $count = $values->count();
        $mean = $count > 0 ? $values->avg() : null;
        $stddev = null;

        if ($count > 0) {
            $variance = $values->map(fn ($v) => pow($v - $mean, 2))->avg();
            $stddev = sqrt($variance);
        }

        return ResultStatistic::updateOrCreate(
            ['measurement_code_id' => $codeId],
            [
                'count'            => $count,
                'mean'             => $mean,
                'stddev'           => $stddev,
                'last_computed_at' => now(),
            ]
        );
    }
}
