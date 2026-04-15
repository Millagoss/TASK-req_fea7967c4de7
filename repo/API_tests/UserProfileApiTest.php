<?php

namespace Tests\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileApiTest extends TestCase
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

    public function test_get_profile(): void
    {
        $user = $this->createAdminUser();

        // Create a profile via recompute first
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/users/{$user->id}/profile/recompute")
            ->assertStatus(200);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/users/{$user->id}/profile");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user_id']]);
    }

    public function test_get_empty_profile(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/users/{$user->id}/profile");

        $response->assertStatus(200)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.interest_tags', null)
            ->assertJsonPath('data.preference_vector', null)
            ->assertJsonPath('data.last_computed_at', null);
    }

    public function test_recompute_profile(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/users/{$user->id}/profile/recompute");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user_id', 'last_computed_at']]);
    }
}
