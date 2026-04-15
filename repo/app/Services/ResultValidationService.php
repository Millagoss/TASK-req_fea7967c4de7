<?php

namespace App\Services;

use App\Models\MeasurementCode;
use App\Models\Result;
use App\Models\ResultStatistic;
use App\Models\Subject;
use App\Models\UnitConversion;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ResultValidationService
{
    /**
     * Validate and process a single result entry.
     *
     * @param array $data Keys: code, subject_identifier, value, unit, observed_at
     * @param int $createdBy User ID
     * @param string $source 'manual'|'csv_import'|'rest_integration'
     * @param string|null $batchId
     * @return array{result: Result, warnings: array}
     * @throws ValidationException
     */
    public function process(array $data, int $createdBy, string $source, ?string $batchId = null): array
    {
        $warnings = [];

        // Step 1: Code exists and is active
        $code = MeasurementCode::where('code', $data['code'])->where('is_active', true)->first();
        if (!$code) {
            throw ValidationException::withMessages(['code' => ["Measurement code '{$data['code']}' not found or inactive."]]);
        }

        // Step 2: Resolve subject
        $subject = Subject::where('identifier', $data['subject_identifier'])->first();
        if (!$subject) {
            throw ValidationException::withMessages(['subject_identifier' => ["Subject '{$data['subject_identifier']}' not found."]]);
        }

        // Step 3: Value type validation
        $valueNumeric = null;
        $valueText = null;
        if ($code->value_type === 'numeric') {
            if (!is_numeric($data['value'])) {
                throw ValidationException::withMessages(['value' => ["Expected numeric value for code '{$code->code}'."]]);
            }
            $valueNumeric = (float) $data['value'];
        } else {
            $valueText = (string) $data['value'];
        }

        // Step 4: Unit normalization
        $unitInput = $data['unit'] ?? $code->unit;
        $unitNormalized = $code->unit;
        if ($unitInput !== $code->unit) {
            $conversion = UnitConversion::where('measurement_code_id', $code->id)
                ->where('from_unit', $unitInput)
                ->first();
            if ($conversion && $valueNumeric !== null) {
                $valueNumeric = $conversion->convert($valueNumeric);
                $unitNormalized = $conversion->to_unit;
            } elseif (!$conversion) {
                $warnings[] = "No conversion found from '{$unitInput}' to '{$code->unit}'. Value stored as-is.";
                $unitNormalized = $unitInput;
            }
        }

        // Step 5: Range check (warning only, don't reject)
        if ($code->value_type === 'numeric' && $valueNumeric !== null) {
            if ($code->reference_range_low !== null && $valueNumeric < (float) $code->reference_range_low) {
                $warnings[] = "Value {$valueNumeric} is below reference range low ({$code->reference_range_low}).";
            }
            if ($code->reference_range_high !== null && $valueNumeric > (float) $code->reference_range_high) {
                $warnings[] = "Value {$valueNumeric} is above reference range high ({$code->reference_range_high}).";
            }
        }

        // Step 6: observed_at validation (cannot be more than 5 min in future)
        $observedAt = Carbon::parse($data['observed_at']);
        $maxAllowed = Carbon::now()->addMinutes(5);
        if ($observedAt->isAfter($maxAllowed)) {
            throw ValidationException::withMessages(['observed_at' => ['observed_at cannot be more than 5 minutes in the future.']]);
        }

        // Step 7: Z-score outlier detection
        $isOutlier = false;
        $zScore = null;
        $outlierThreshold = 3.0;
        if ($code->value_type === 'numeric' && $valueNumeric !== null) {
            $stats = ResultStatistic::where('measurement_code_id', $code->id)->first();
            if ($stats && $stats->count >= 30 && $stats->stddev > 0) {
                $zScore = ($valueNumeric - (float) $stats->mean) / (float) $stats->stddev;
                if (abs($zScore) >= $outlierThreshold) {
                    $isOutlier = true;
                }
            }
        }

        // Step 8: Determine review_status
        $reviewStatus = $isOutlier ? 'pending' : 'approved';

        // Step 9: Create result
        $result = Result::create([
            'subject_id'          => $subject->id,
            'measurement_code_id' => $code->id,
            'value_raw'           => (string) $data['value'],
            'value_numeric'       => $valueNumeric,
            'value_text'          => $valueText,
            'unit_input'          => $unitInput,
            'unit_normalized'     => $unitNormalized,
            'observed_at'         => $observedAt,
            'source'              => $source,
            'is_outlier'          => $isOutlier,
            'z_score'             => $zScore,
            'outlier_threshold'   => $outlierThreshold,
            'review_status'       => $reviewStatus,
            'batch_id'            => $batchId,
            'created_by'          => $createdBy,
        ]);

        return ['result' => $result, 'warnings' => $warnings];
    }
}
