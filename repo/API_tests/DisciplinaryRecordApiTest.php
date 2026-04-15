<?php

namespace Tests\Api;

use App\Models\DisciplinaryRecord;
use App\Models\EvaluationCycle;
use App\Models\Permission;
use App\Models\RewardPenaltyType;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisciplinaryRecordApiTest extends TestCase
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

    private function createPenaltyType(): RewardPenaltyType
    {
        return RewardPenaltyType::create([
            'name'                    => 'Warning_' . uniqid(),
            'category'                => 'penalty',
            'severity'                => 'low',
            'default_points'          => 5,
            'default_expiration_days' => 30,
            'is_active'               => true,
        ]);
    }

    public function test_create_disciplinary_record(): void
    {
        $admin = $this->createAdminUser();
        $subject = User::create([
            'username'      => 'subject_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'Subject',
            'is_active'     => true,
        ]);
        $type = $this->createPenaltyType();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/disciplinary-records', [
                'type_id'         => $type->id,
                'subject_user_id' => $subject->id,
                'reason'          => 'Test violation',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_appeal_active_record(): void
    {
        $admin = $this->createAdminUser();
        $subject = User::create([
            'username'      => 'subject_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'Subject',
            'is_active'     => true,
        ]);
        $type = $this->createPenaltyType();
        $record = DisciplinaryRecord::create([
            'type_id'         => $type->id,
            'subject_user_id' => $subject->id,
            'issuer_user_id'  => $admin->id,
            'status'          => 'active',
            'reason'          => 'Test',
            'points'          => 5,
            'issued_at'       => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/disciplinary-records/{$record->id}/appeal", [
                'appeal_reason' => 'I disagree',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'appealed');
    }

    public function test_clear_appealed_record(): void
    {
        $admin = $this->createAdminUser();
        $subject = User::create([
            'username'      => 'subject_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'Subject',
            'is_active'     => true,
        ]);
        $type = $this->createPenaltyType();
        $record = DisciplinaryRecord::create([
            'type_id'         => $type->id,
            'subject_user_id' => $subject->id,
            'issuer_user_id'  => $admin->id,
            'status'          => 'appealed',
            'reason'          => 'Test',
            'points'          => 5,
            'issued_at'       => now(),
            'appealed_at'     => now(),
            'appeal_reason'   => 'Please clear',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/disciplinary-records/{$record->id}/clear", [
                'cleared_reason' => 'Appeal accepted',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cleared');
    }

    public function test_cannot_appeal_cleared_record(): void
    {
        $admin = $this->createAdminUser();
        $subject = User::create([
            'username'      => 'subject_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'Subject',
            'is_active'     => true,
        ]);
        $type = $this->createPenaltyType();
        $record = DisciplinaryRecord::create([
            'type_id'         => $type->id,
            'subject_user_id' => $subject->id,
            'issuer_user_id'  => $admin->id,
            'status'          => 'cleared',
            'reason'          => 'Test',
            'points'          => 5,
            'issued_at'       => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/disciplinary-records/{$record->id}/appeal", [
                'appeal_reason' => 'Too late',
            ]);

        $response->assertStatus(422);
    }

    public function test_stats_by_category(): void
    {
        $admin = $this->createAdminUser();
        $subject = User::create([
            'username'      => 'subject_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'Subject',
            'is_active'     => true,
        ]);
        $penaltyType = $this->createPenaltyType();
        $rewardType = RewardPenaltyType::create([
            'name'                    => 'Reward_' . uniqid(),
            'category'                => 'reward',
            'default_points'          => 10,
            'default_expiration_days' => 365,
            'is_active'               => true,
        ]);

        DisciplinaryRecord::create([
            'type_id' => $penaltyType->id, 'subject_user_id' => $subject->id,
            'issuer_user_id' => $admin->id, 'status' => 'active',
            'reason' => 'r1', 'points' => 5, 'issued_at' => now(),
        ]);
        DisciplinaryRecord::create([
            'type_id' => $rewardType->id, 'subject_user_id' => $subject->id,
            'issuer_user_id' => $admin->id, 'status' => 'active',
            'reason' => 'r2', 'points' => 10, 'issued_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/disciplinary-records/stats?group_by=category');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_subject_can_appeal_own_record(): void
    {
        $admin = $this->createAdminUser();
        $subject = User::create([
            'username'      => 'subject_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'Subject',
            'is_active'     => true,
        ]);
        $type = $this->createPenaltyType();
        $record = DisciplinaryRecord::create([
            'type_id'         => $type->id,
            'subject_user_id' => $subject->id,
            'issuer_user_id'  => $admin->id,
            'status'          => 'active',
            'reason'          => 'Test',
            'points'          => 5,
            'issued_at'       => now(),
        ]);

        $response = $this->actingAs($subject, 'sanctum')
            ->postJson("/api/v1/disciplinary-records/{$record->id}/appeal", [
                'appeal_reason' => 'Self appeal',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'appealed');
    }
}
