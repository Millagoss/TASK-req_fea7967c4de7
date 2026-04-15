<?php

namespace Tests\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleApiTest extends TestCase
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

    public function test_list_roles(): void
    {
        $this->createAdminUser();

        $response = $this->actingAsAdmin()
            ->getJson('/api/v1/admin/roles');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_create_role(): void
    {
        $this->createAdminUser();
        Permission::firstOrCreate(['name' => 'users.list'], ['description' => 'users.list']);

        $response = $this->actingAsAdmin()
            ->postJson('/api/v1/admin/roles', [
                'name'        => 'testrole_' . uniqid(),
                'description' => 'A test role',
                'permissions' => ['users.list'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('description', 'A test role');
    }

    public function test_update_role(): void
    {
        $admin = $this->createAdminUser();
        $role = Role::create(['name' => 'updatable_' . uniqid(), 'description' => 'Old']);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/roles/{$role->id}", [
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('description', 'Updated description');
    }

    public function test_assign_permissions(): void
    {
        $admin = $this->createAdminUser();
        $role = Role::create(['name' => 'permtest_' . uniqid(), 'description' => 'Perm test']);
        Permission::firstOrCreate(['name' => 'users.create'], ['description' => 'users.create']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/roles/{$role->id}/permissions", [
                'permissions' => ['users.create'],
            ]);

        $response->assertStatus(200);
        $this->assertTrue($role->fresh()->permissions->contains('name', 'users.create'));
    }

    public function test_list_permissions(): void
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/v1/admin/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }
}
