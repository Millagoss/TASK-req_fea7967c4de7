<?php

namespace Tests\Api;

use App\Models\LeaderProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderProfileApiTest extends TestCase
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

    public function test_create_leader_profile(): void
    {
        $admin = $this->createAdminUser();
        $targetUser = User::create([
            'username'      => 'leader_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'Leader',
            'is_active'     => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/leader-profiles', [
                'user_id'    => $targetUser->id,
                'title'      => 'Director',
                'department' => 'Engineering',
                'campus'     => 'Main',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Director');
    }

    public function test_list_leader_profiles(): void
    {
        $admin = $this->createAdminUser();
        $targetUser = User::create([
            'username'      => 'leader2_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'Leader2',
            'is_active'     => true,
        ]);
        LeaderProfile::create([
            'user_id'    => $targetUser->id,
            'title'      => 'Manager',
            'department' => 'HR',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/leader-profiles');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_update_leader_profile(): void
    {
        $admin = $this->createAdminUser();
        $targetUser = User::create([
            'username'      => 'leader3_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'Leader3',
            'is_active'     => true,
        ]);
        $profile = LeaderProfile::create([
            'user_id'    => $targetUser->id,
            'title'      => 'Old Title',
            'department' => 'Old Dept',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/leader-profiles/{$profile->id}", [
                'title' => 'New Title',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'New Title');
    }
}
