<?php

namespace Tests\Api;

use App\Models\MeasurementCode;
use App\Models\Permission;
use App\Models\Result;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ResultApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        $user = User::create([
            'username'      => 'admin_' . uniqid(),
            'password_hash' => AuthService::makeHash('Admin@Password1'),
            'display_name'  => 'Admin',
            'is_active'     => true,
        ]);
        $role = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        $permissions = Permission::all();
        if ($permissions->isEmpty()) {
            foreach (['users.list','users.create','users.update','roles.list','roles.create','roles.update','service_accounts.create','disciplinary.appeal','disciplinary.clear','results.review','subjects.view_pii','music.read','music.create','music.update','music.delete','music.publish','music.manage_all'] as $p) {
                Permission::firstOrCreate(['name' => $p], ['description' => $p]);
            }
            $permissions = Permission::all();
        }
        $role->permissions()->sync($permissions->pluck('id'));
        $user->roles()->sync([$role->id]);
        return $user;
    }

    private function actingAsAdmin()
    {
        $user = $this->createAdminUser();
        return $this->actingAs($user, 'sanctum');
    }

    private function seedCodeAndSubject(): array
    {
        $code = MeasurementCode::create([
            'code'                 => 'GLU_' . uniqid(),
            'display_name'         => 'Glucose',
            'unit'                 => 'mg/dL',
            'value_type'           => 'numeric',
            'reference_range_low'  => 70,
            'reference_range_high' => 100,
            'is_active'            => true,
        ]);
        $subject = Subject::create([
            'identifier' => 'SUBJ_' . uniqid(),
            'name'       => 'Test Subject',
        ]);
        return [$code, $subject];
    }

    public function test_manual_result_entry(): void
    {
        $admin = $this->createAdminUser();
        [$code, $subject] = $this->seedCodeAndSubject();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/results', [
                'code'               => $code->code,
                'subject_identifier' => $subject->identifier,
                'value'              => 85,
                'unit'               => 'mg/dL',
                'observed_at'        => now()->toIso8601String(),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'warnings']);
    }

    public function test_batch_result_entry(): void
    {
        $admin = $this->createAdminUser();
        [$code, $subject] = $this->seedCodeAndSubject();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/results/batch', [
                'observations' => [
                    [
                        'code'               => $code->code,
                        'subject_identifier' => $subject->identifier,
                        'value'              => 90,
                        'unit'               => 'mg/dL',
                        'observed_at'        => now()->toIso8601String(),
                    ],
                    [
                        'code'               => $code->code,
                        'subject_identifier' => $subject->identifier,
                        'value'              => 95,
                        'unit'               => 'mg/dL',
                        'observed_at'        => now()->subHour()->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('imported', 2)
            ->assertJsonStructure(['batch_id', 'imported', 'errors', 'results']);
    }

    public function test_csv_import(): void
    {
        $admin = $this->createAdminUser();
        [$code, $subject] = $this->seedCodeAndSubject();

        $csvContent = "code,subject_identifier,value,unit,observed_at\n"
            . "{$code->code},{$subject->identifier},88,mg/dL," . now()->toDateTimeString();

        $file = UploadedFile::fake()->createWithContent('results.csv', $csvContent);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/results/import-csv', [
                'csv_file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('imported', 1)
            ->assertJsonStructure(['batch_id', 'imported', 'errors']);
    }

    public function test_list_results(): void
    {
        $admin = $this->createAdminUser();
        [$code, $subject] = $this->seedCodeAndSubject();
        Result::create([
            'subject_id'          => $subject->id,
            'measurement_code_id' => $code->id,
            'value_raw'           => '85',
            'value_numeric'       => 85,
            'unit_input'          => 'mg/dL',
            'unit_normalized'     => 'mg/dL',
            'observed_at'         => now(),
            'source'              => 'manual',
            'is_outlier'          => false,
            'review_status'       => 'approved',
            'created_by'          => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/results');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_flagged_results(): void
    {
        $admin = $this->createAdminUser();
        [$code, $subject] = $this->seedCodeAndSubject();
        Result::create([
            'subject_id'          => $subject->id,
            'measurement_code_id' => $code->id,
            'value_raw'           => '999',
            'value_numeric'       => 999,
            'unit_input'          => 'mg/dL',
            'unit_normalized'     => 'mg/dL',
            'observed_at'         => now(),
            'source'              => 'manual',
            'is_outlier'          => true,
            'review_status'       => 'pending',
            'created_by'          => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/results/flagged');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_review_result(): void
    {
        $admin = $this->createAdminUser();
        $otherUser = User::create([
            'username'      => 'creator_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'Creator',
            'is_active'     => true,
        ]);
        [$code, $subject] = $this->seedCodeAndSubject();
        $result = Result::create([
            'subject_id'          => $subject->id,
            'measurement_code_id' => $code->id,
            'value_raw'           => '85',
            'value_numeric'       => 85,
            'unit_input'          => 'mg/dL',
            'unit_normalized'     => 'mg/dL',
            'observed_at'         => now(),
            'source'              => 'manual',
            'is_outlier'          => true,
            'review_status'       => 'pending',
            'created_by'          => $otherUser->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/results/{$result->id}/review", [
                'decision'       => 'approved',
                'review_comment' => 'Looks good',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.review_status', 'approved');
    }

    public function test_cannot_self_review(): void
    {
        $admin = $this->createAdminUser();
        [$code, $subject] = $this->seedCodeAndSubject();
        $result = Result::create([
            'subject_id'          => $subject->id,
            'measurement_code_id' => $code->id,
            'value_raw'           => '85',
            'value_numeric'       => 85,
            'unit_input'          => 'mg/dL',
            'unit_normalized'     => 'mg/dL',
            'observed_at'         => now(),
            'source'              => 'manual',
            'is_outlier'          => true,
            'review_status'       => 'pending',
            'created_by'          => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/results/{$result->id}/review", [
                'decision' => 'approved',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_recompute_stats(): void
    {
        $admin = $this->createAdminUser();
        [$code, $subject] = $this->seedCodeAndSubject();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/results/recompute-stats');

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'codes_updated']);
    }
}
