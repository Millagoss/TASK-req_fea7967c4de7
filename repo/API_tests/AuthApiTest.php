<?php

namespace Tests\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
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

    public function test_login_success(): void
    {
        $user = User::create([
            'username'      => 'testlogin',
            'password_hash' => AuthService::makeHash('ValidPassword123!'),
            'display_name'  => 'Test Login',
            'is_active'     => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testlogin',
            'password' => 'ValidPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type', 'user'])
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_login_wrong_password(): void
    {
        User::create([
            'username'      => 'testlogin2',
            'password_hash' => AuthService::makeHash('ValidPassword123!'),
            'display_name'  => 'Test',
            'is_active'     => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testlogin2',
            'password' => 'WrongPassword999!',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_validation_short_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => '',
            'password' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_me_returns_user_with_roles(): void
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('username', $user->username);
    }

    public function test_me_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::create([
            'username'      => 'logouttest',
            'password_hash' => AuthService::makeHash('ValidPassword123!'),
            'display_name'  => 'Logout Test',
            'is_active'     => true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('msg', 'Logged out successfully.');

        $meResponse = $this->withToken($token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(401);
    }

    public function test_logout_all_revokes_all_tokens(): void
    {
        $user = User::create([
            'username'      => 'logoutalltest',
            'password_hash' => AuthService::makeHash('ValidPassword123!'),
            'display_name'  => 'Logout All',
            'is_active'     => true,
        ]);
        $token1 = $user->createToken('test1')->plainTextToken;
        $token2 = $user->createToken('test2')->plainTextToken;

        $response = $this->withToken($token1)
            ->postJson('/api/v1/auth/logout-all');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'All sessions terminated.');

        $this->withToken($token2)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
