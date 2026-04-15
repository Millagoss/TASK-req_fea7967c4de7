<?php

namespace Tests\Api;

use App\Models\MeasurementCode;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementCodeApiTest extends TestCase
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

    public function test_create_measurement_code(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/v1/measurement-codes', [
                'code'                 => 'HGB_' . uniqid(),
                'display_name'         => 'Hemoglobin',
                'unit'                 => 'g/dL',
                'value_type'           => 'numeric',
                'reference_range_low'  => 12.0,
                'reference_range_high' => 17.5,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.display_name', 'Hemoglobin');
    }

    public function test_list_measurement_codes(): void
    {
        $user = $this->createAdminUser();
        MeasurementCode::create([
            'code' => 'WBC_' . uniqid(), 'display_name' => 'White Blood Cells',
            'unit' => 'K/uL', 'value_type' => 'numeric', 'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/measurement-codes');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_show_measurement_code_with_conversions(): void
    {
        $user = $this->createAdminUser();
        $code = MeasurementCode::create([
            'code' => 'SHOW_' . uniqid(), 'display_name' => 'Show Code',
            'unit' => 'mg/dL', 'value_type' => 'numeric', 'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/measurement-codes/{$code->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.code', $code->code);
    }

    public function test_update_measurement_code(): void
    {
        $admin = $this->createAdminUser();
        $code = MeasurementCode::create([
            'code' => 'UPD_' . uniqid(), 'display_name' => 'Old Name',
            'unit' => 'mg/dL', 'value_type' => 'numeric', 'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/measurement-codes/{$code->id}", [
                'display_name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.display_name', 'Updated Name');
    }
}
