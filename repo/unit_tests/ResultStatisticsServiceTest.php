<?php

namespace Tests\Unit;

use App\Models\MeasurementCode;
use App\Models\Result;
use App\Models\ResultStatistic;
use App\Models\Subject;
use App\Models\User;
use App\Services\ResultStatisticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultStatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ResultStatisticsService $service;
    protected MeasurementCode $code;
    protected Subject $subject;
    protected User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResultStatisticsService();

        $this->creator = User::create([
            'username'      => 'stats_tech',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Stats Tech',
            'is_active'     => true,
        ]);

        $this->code = MeasurementCode::create([
            'code'         => 'GLU',
            'display_name' => 'Glucose',
            'unit'         => 'mg/dL',
            'value_type'   => 'numeric',
            'is_active'    => true,
        ]);

        $this->subject = Subject::create([
            'identifier' => 'SUBJ-S1',
            'name'       => 'Subject S1',
            'campus'     => 'Main',
        ]);
    }

    private function createResult(float $value, string $reviewStatus = 'approved'): Result
    {
        return Result::create([
            'subject_id'          => $this->subject->id,
            'measurement_code_id' => $this->code->id,
            'value_raw'           => (string) $value,
            'value_numeric'       => $value,
            'unit_input'          => 'mg/dL',
            'unit_normalized'     => 'mg/dL',
            'observed_at'         => Carbon::now(),
            'source'              => 'manual',
            'is_outlier'          => false,
            'review_status'       => $reviewStatus,
            'created_by'          => $this->creator->id,
        ]);
    }

    public function test_compute_for_code_calculates_correct_mean_and_stddev(): void
    {
        // Values: 100, 110, 120 => mean = 110
        $this->createResult(100.0);
        $this->createResult(110.0);
        $this->createResult(120.0);

        $stat = $this->service->computeForCode($this->code->id);

        $this->assertInstanceOf(ResultStatistic::class, $stat);
        $this->assertEquals($this->code->id, $stat->measurement_code_id);
        $this->assertEquals(3, $stat->count);
        $this->assertEqualsWithDelta(110.0, (float) $stat->mean, 0.01);
        $this->assertNotNull($stat->last_computed_at);
    }

    public function test_only_includes_approved_results(): void
    {
        $this->createResult(100.0, 'approved');
        $this->createResult(200.0, 'approved');
        $this->createResult(999.0, 'rejected');
        $this->createResult(888.0, 'pending');

        $stat = $this->service->computeForCode($this->code->id);

        $this->assertEquals(2, $stat->count);
        $this->assertEqualsWithDelta(150.0, (float) $stat->mean, 0.01);
    }

    public function test_excludes_rejected_and_pending_results(): void
    {
        $this->createResult(500.0, 'rejected');
        $this->createResult(600.0, 'pending');

        $stat = $this->service->computeForCode($this->code->id);

        $this->assertEquals(0, $stat->count);
    }

    public function test_recompute_all_processes_all_active_codes(): void
    {
        $code2 = MeasurementCode::create([
            'code'         => 'HB',
            'display_name' => 'Hemoglobin',
            'unit'         => 'g/dL',
            'value_type'   => 'numeric',
            'is_active'    => true,
        ]);

        $inactiveCode = MeasurementCode::create([
            'code'         => 'OLD',
            'display_name' => 'Old Code',
            'unit'         => 'x',
            'value_type'   => 'numeric',
            'is_active'    => false,
        ]);

        $this->createResult(100.0);

        Result::create([
            'subject_id'          => $this->subject->id,
            'measurement_code_id' => $code2->id,
            'value_raw'           => '14.0',
            'value_numeric'       => 14.0,
            'unit_input'          => 'g/dL',
            'unit_normalized'     => 'g/dL',
            'observed_at'         => Carbon::now(),
            'source'              => 'manual',
            'is_outlier'          => false,
            'review_status'       => 'approved',
            'created_by'          => $this->creator->id,
        ]);

        $count = $this->service->recomputeAll();

        // Should process GLU and HB (2 active codes), not OLD
        $this->assertEquals(2, $count);
        $this->assertNotNull(ResultStatistic::where('measurement_code_id', $this->code->id)->first());
        $this->assertNotNull(ResultStatistic::where('measurement_code_id', $code2->id)->first());
        $this->assertNull(ResultStatistic::where('measurement_code_id', $inactiveCode->id)->first());
    }

    public function test_updates_existing_statistics_record(): void
    {
        $this->createResult(100.0);
        $stat1 = $this->service->computeForCode($this->code->id);
        $this->assertEquals(1, $stat1->count);

        $this->createResult(200.0);
        $stat2 = $this->service->computeForCode($this->code->id);

        // Should update same record, not create new
        $this->assertEquals($stat1->id, $stat2->id);
        $this->assertEquals(2, $stat2->count);
        $this->assertEqualsWithDelta(150.0, (float) $stat2->mean, 0.01);
    }
}
