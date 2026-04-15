<?php

namespace Tests\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectApiTest extends TestCase
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

    public function test_create_subject(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/v1/subjects', [
                'identifier' => 'SUBJ_' . uniqid(),
                'name'       => 'Jane Doe',
                'campus'     => 'Main Campus',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'identifier', 'name']]);
    }

    public function test_list_subjects_pii_masked(): void
    {
        // User without subjects.view_pii permission
        $user = User::create([
            'username'      => 'nopii_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'No PII',
            'is_active'     => true,
        ]);
        Subject::create([
            'identifier' => 'ID12345678',
            'name'       => 'Sensitive Name',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/subjects');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        // PII should be masked
        $this->assertEquals('***5678', $data[0]['identifier']);
        $this->assertEquals('***', $data[0]['name']);
    }

    public function test_list_subjects_pii_visible(): void
    {
        $admin = $this->createAdminUser(); // has subjects.view_pii
        Subject::create([
            'identifier' => 'ID12345678',
            'name'       => 'Visible Name',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/subjects');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('ID12345678', $data[0]['identifier']);
        $this->assertEquals('Visible Name', $data[0]['name']);
    }

    public function test_update_subject(): void
    {
        $admin = $this->createAdminUser();
        $subject = Subject::create([
            'identifier' => 'UPD_' . uniqid(),
            'name'       => 'Old Name',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/subjects/{$subject->id}", [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }
}
