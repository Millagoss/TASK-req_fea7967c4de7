<?php

namespace Tests\Api;

use App\Models\Permission;
use App\Models\RewardPenaltyType;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardPenaltyTypeApiTest extends TestCase
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

    public function test_create_reward_type(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/v1/reward-penalty-types', [
                'name'           => 'Bonus_' . uniqid(),
                'category'       => 'reward',
                'default_points' => 10,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.category', 'reward');
    }

    public function test_create_penalty_type(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/v1/reward-penalty-types', [
                'name'           => 'Warning_' . uniqid(),
                'category'       => 'penalty',
                'severity'       => 'high',
                'default_points' => 5,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.category', 'penalty');
    }

    public function test_list_types_filter_by_category(): void
    {
        $user = $this->createAdminUser();
        RewardPenaltyType::create([
            'name' => 'R1', 'category' => 'reward', 'default_points' => 10, 'is_active' => true,
        ]);
        RewardPenaltyType::create([
            'name' => 'P1', 'category' => 'penalty', 'default_points' => 5, 'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reward-penalty-types?category=reward');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals('reward', $item['category']);
        }
    }

    public function test_update_type(): void
    {
        $admin = $this->createAdminUser();
        $type = RewardPenaltyType::create([
            'name' => 'Updatable', 'category' => 'penalty', 'default_points' => 3, 'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/reward-penalty-types/{$type->id}", [
                'name' => 'Updated Type',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Type');
    }
}
