<?php

namespace Tests\Api;

use App\Models\EvaluationCycle;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationCycleApiTest extends TestCase
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

    public function test_create_evaluation_cycle(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/v1/evaluation-cycles', [
                'name'       => 'Q1 2026',
                'start_date' => '2026-01-01',
                'end_date'   => '2026-03-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_list_evaluation_cycles(): void
    {
        $user = $this->createAdminUser();
        EvaluationCycle::create([
            'name' => 'Q1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31',
            'status' => 'draft', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/evaluation-cycles');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_activate_cycle(): void
    {
        $user = $this->createAdminUser();
        $cycle = EvaluationCycle::create([
            'name' => 'Activate', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31',
            'status' => 'draft', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/evaluation-cycles/{$cycle->id}/activate");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_close_cycle(): void
    {
        $user = $this->createAdminUser();
        $cycle = EvaluationCycle::create([
            'name' => 'Close', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31',
            'status' => 'active', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/evaluation-cycles/{$cycle->id}/close");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_cannot_activate_non_draft(): void
    {
        $user = $this->createAdminUser();
        $cycle = EvaluationCycle::create([
            'name' => 'Active', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31',
            'status' => 'active', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/evaluation-cycles/{$cycle->id}/activate");

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_update_only_draft(): void
    {
        $user = $this->createAdminUser();
        $cycle = EvaluationCycle::create([
            'name' => 'Active Cycle', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31',
            'status' => 'active', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/evaluation-cycles/{$cycle->id}", [
                'name' => 'Changed',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }
}
