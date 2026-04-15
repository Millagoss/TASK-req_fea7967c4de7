<?php

namespace Tests\Unit;

use App\Models\MeasurementCode;
use App\Models\Result;
use App\Models\ResultStatistic;
use App\Models\Subject;
use App\Models\UnitConversion;
use App\Models\User;
use App\Services\ResultValidationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResultValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ResultValidationService $service;
    protected User $creator;
    protected MeasurementCode $code;
    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResultValidationService();

        $this->creator = User::create([
            'username'      => 'lab_tech',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Lab Tech',
            'is_active'     => true,
        ]);

        $this->code = MeasurementCode::create([
            'code'                 => 'TEMP_C',
            'display_name'         => 'Temperature Celsius',
            'unit'                 => 'C',
            'value_type'           => 'numeric',
            'reference_range_low'  => 35.0,
            'reference_range_high' => 42.0,
            'is_active'            => true,
        ]);

        $this->subject = Subject::create([
            'identifier' => 'SUBJ-001',
            'name'       => 'Test Subject',
            'campus'     => 'Main',
        ]);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'code'               => 'TEMP_C',
            'subject_identifier' => 'SUBJ-001',
            'value'              => '37.5',
            'unit'               => 'C',
            'observed_at'        => Carbon::now()->toIso8601String(),
        ], $overrides);
    }

    public function test_processes_valid_numeric_result(): void
    {
        $output = $this->service->process($this->validData(), $this->creator->id, 'manual');

        $this->assertInstanceOf(Result::class, $output['result']);
        $this->assertIsArray($output['warnings']);
        $this->assertEmpty($output['warnings']);
        $this->assertEquals('37.5', $output['result']->value_raw);
        $this->assertEquals(37.5, (float) $output['result']->value_numeric);
        $this->assertNull($output['result']->value_text);
        $this->assertEquals('approved', $output['result']->review_status);
        $this->assertFalse($output['result']->is_outlier);
    }

    public function test_rejects_inactive_measurement_code(): void
    {
        $this->code->update(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->service->process($this->validData(), $this->creator->id, 'manual');
    }

    public function test_rejects_unknown_subject(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->process(
            $this->validData(['subject_identifier' => 'NONEXISTENT']),
            $this->creator->id,
            'manual'
        );
    }

    public function test_rejects_non_numeric_value_for_numeric_code(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->process(
            $this->validData(['value' => 'not-a-number']),
            $this->creator->id,
            'manual'
        );
    }

    public function test_normalizes_unit_via_conversion_factor_and_offset(): void
    {
        UnitConversion::create([
            'measurement_code_id' => $this->code->id,
            'from_unit'           => 'F',
            'to_unit'             => 'C',
            'factor'              => 0.55555556,
            'offset'              => -17.77777778,
        ]);

        $output = $this->service->process(
            $this->validData(['value' => '98.6', 'unit' => 'F']),
            $this->creator->id,
            'manual'
        );

        $result = $output['result'];
        $this->assertEquals('F', $result->unit_input);
        $this->assertEquals('C', $result->unit_normalized);
        // 98.6 * 0.55555556 + (-17.77777778) ~ 37.0
        $this->assertEqualsWithDelta(37.0, (float) $result->value_numeric, 0.1);
    }

    public function test_warns_when_value_below_reference_range(): void
    {
        $output = $this->service->process(
            $this->validData(['value' => '30.0']),
            $this->creator->id,
            'manual'
        );

        $this->assertNotEmpty($output['warnings']);
        $this->assertStringContainsString('below reference range', $output['warnings'][0]);
    }

    public function test_warns_when_value_above_reference_range(): void
    {
        $output = $this->service->process(
            $this->validData(['value' => '50.0']),
            $this->creator->id,
            'manual'
        );

        $this->assertNotEmpty($output['warnings']);
        $this->assertStringContainsString('above reference range', $output['warnings'][0]);
    }

    public function test_rejects_observed_at_more_than_5_min_in_future(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->process(
            $this->validData(['observed_at' => Carbon::now()->addMinutes(10)->toIso8601String()]),
            $this->creator->id,
            'manual'
        );
    }

    public function test_detects_z_score_outlier(): void
    {
        // Create statistics with mean=37.0, stddev=1.0, count=50
        ResultStatistic::create([
            'measurement_code_id' => $this->code->id,
            'count'               => 50,
            'mean'                => 37.0,
            'stddev'              => 1.0,
            'last_computed_at'    => Carbon::now(),
        ]);

        // Value of 45.0 => z-score = (45-37)/1 = 8.0 > 3.0 threshold
        $output = $this->service->process(
            $this->validData(['value' => '45.0']),
            $this->creator->id,
            'manual'
        );

        $result = $output['result'];
        $this->assertTrue($result->is_outlier);
        $this->assertEquals('pending', $result->review_status);
        $this->assertEqualsWithDelta(8.0, (float) $result->z_score, 0.01);
    }

    public function test_skips_z_score_when_count_less_than_30(): void
    {
        ResultStatistic::create([
            'measurement_code_id' => $this->code->id,
            'count'               => 10,
            'mean'                => 37.0,
            'stddev'              => 1.0,
            'last_computed_at'    => Carbon::now(),
        ]);

        // Even extreme value should not be flagged
        $output = $this->service->process(
            $this->validData(['value' => '99.0']),
            $this->creator->id,
            'manual'
        );

        $this->assertFalse($output['result']->is_outlier);
        $this->assertEquals('approved', $output['result']->review_status);
        $this->assertNull($output['result']->z_score);
    }

    public function test_handles_text_value_type(): void
    {
        $textCode = MeasurementCode::create([
            'code'         => 'COLOR',
            'display_name' => 'Color Assessment',
            'unit'         => 'n/a',
            'value_type'   => 'text',
            'is_active'    => true,
        ]);

        $output = $this->service->process(
            [
                'code'               => 'COLOR',
                'subject_identifier' => 'SUBJ-001',
                'value'              => 'dark amber',
                'unit'               => 'n/a',
                'observed_at'        => Carbon::now()->toIso8601String(),
            ],
            $this->creator->id,
            'manual'
        );

        $result = $output['result'];
        $this->assertNull($result->value_numeric);
        $this->assertEquals('dark amber', $result->value_text);
        $this->assertEquals('dark amber', $result->value_raw);
    }

    public function test_handles_coded_value_type(): void
    {
        $codedCode = MeasurementCode::create([
            'code'         => 'STATUS',
            'display_name' => 'Status Code',
            'unit'         => 'n/a',
            'value_type'   => 'coded',
            'is_active'    => true,
        ]);

        $output = $this->service->process(
            [
                'code'               => 'STATUS',
                'subject_identifier' => 'SUBJ-001',
                'value'              => 'POS',
                'unit'               => 'n/a',
                'observed_at'        => Carbon::now()->toIso8601String(),
            ],
            $this->creator->id,
            'manual'
        );

        $result = $output['result'];
        $this->assertNull($result->value_numeric);
        $this->assertEquals('POS', $result->value_text);
    }

    public function test_stores_value_raw_correctly(): void
    {
        $output = $this->service->process(
            $this->validData(['value' => '  37.500  ']),
            $this->creator->id,
            'manual'
        );

        $this->assertEquals('  37.500  ', $output['result']->value_raw);
    }
}
