<?php

namespace Tests\Api;

use App\Models\BehaviorEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BehaviorEventApiTest extends TestCase
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

    public function test_record_event(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/behavior/events', [
                'event_type'  => 'click',
                'target_type' => 'song',
                'target_id'   => 1,
                'payload'     => ['source' => 'search'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data']);
    }

    public function test_dedup_within_5_seconds(): void
    {
        $user = $this->createAdminUser();

        // First event
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/behavior/events', [
                'event_type'  => 'click',
                'target_type' => 'song',
                'target_id'   => 99,
            ])
            ->assertStatus(201);

        // Same event within 5 seconds
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/behavior/events', [
                'event_type'  => 'click',
                'target_type' => 'song',
                'target_id'   => 99,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('deduplicated', true);
    }

    public function test_list_events_requires_permission(): void
    {
        $user = User::create([
            'username'      => 'noperm_' . uniqid(),
            'password_hash' => AuthService::makeHash('SomePassword123!'),
            'display_name'  => 'No Perm',
            'is_active'     => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/behavior/events');

        $response->assertStatus(403);
    }

    public function test_filter_events_by_type(): void
    {
        $user = $this->createAdminUser();

        BehaviorEvent::create([
            'user_id'          => $user->id,
            'event_type'       => 'browse',
            'target_type'      => 'song',
            'target_id'        => 1,
            'server_timestamp' => now(),
        ]);
        BehaviorEvent::create([
            'user_id'          => $user->id,
            'event_type'       => 'click',
            'target_type'      => 'song',
            'target_id'        => 2,
            'server_timestamp' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/behavior/events?event_type=browse');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
    }
}
