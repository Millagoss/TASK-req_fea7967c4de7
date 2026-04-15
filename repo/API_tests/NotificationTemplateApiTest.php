<?php

namespace Tests\Api;

use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;
    protected string $adminToken;
    protected string $regularToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $rolesCreate = Permission::firstOrCreate(['name' => 'roles.create']);
        $rolesUpdate = Permission::firstOrCreate(['name' => 'roles.update']);

        // Create admin role
        $adminRole = Role::create(['name' => 'admin_test', 'description' => 'Admin']);
        $adminRole->permissions()->sync([$rolesCreate->id, $rolesUpdate->id]);

        // Create admin user
        $this->adminUser = User::create([
            'username'      => 'api_admin',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'API Admin',
            'is_active'     => true,
        ]);
        $this->adminUser->roles()->attach($adminRole->id);
        $this->adminToken = $this->adminUser->createToken('test')->plainTextToken;

        // Create regular user (no permissions)
        $this->regularUser = User::create([
            'username'      => 'api_regular',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Regular User',
            'is_active'     => true,
        ]);
        $this->regularToken = $this->regularUser->createToken('test')->plainTextToken;
    }

    public function test_list_templates_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/notification-templates');
        $response->assertStatus(401);
    }

    public function test_list_templates_returns_paginated_list(): void
    {
        NotificationTemplate::create([
            'name'       => 'alpha_template',
            'subject'    => 'Subject',
            'body'       => 'Body',
            'variables'  => [],
            'created_by' => $this->adminUser->id,
        ]);

        NotificationTemplate::create([
            'name'       => 'beta_template',
            'subject'    => 'Subject 2',
            'body'       => 'Body 2',
            'variables'  => ['var1'],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->withToken($this->regularToken)
            ->getJson('/api/v1/notification-templates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'subject', 'body', 'variables', 'created_by', 'created_at', 'updated_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        // Ordered by name
        $data = $response->json('data');
        $this->assertEquals('alpha_template', $data[0]['name']);
        $this->assertEquals('beta_template', $data[1]['name']);
    }

    public function test_create_template_requires_permission(): void
    {
        $response = $this->withToken($this->regularToken)
            ->postJson('/api/v1/notification-templates', [
                'name'      => 'test',
                'subject'   => 'Subject',
                'body'      => 'Body',
                'variables' => [],
            ]);

        $response->assertStatus(403);
    }

    public function test_create_template_succeeds_with_permission(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/notification-templates', [
                'name'      => 'welcome_email',
                'subject'   => 'Welcome {{username}}',
                'body'      => 'Hello {{username}}, welcome!',
                'variables' => ['username'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'welcome_email')
            ->assertJsonPath('data.created_by', $this->adminUser->id);

        $this->assertDatabaseHas('notification_templates', ['name' => 'welcome_email']);
    }

    public function test_create_template_validates_unique_name(): void
    {
        NotificationTemplate::create([
            'name'       => 'duplicate',
            'subject'    => 'S',
            'body'       => 'B',
            'variables'  => [],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/notification-templates', [
                'name'      => 'duplicate',
                'subject'   => 'S2',
                'body'      => 'B2',
                'variables' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_update_template_succeeds(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'to_update',
            'subject'    => 'Old Subject',
            'body'       => 'Old Body',
            'variables'  => ['old_var'],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/notification-templates/{$template->id}", [
                'subject' => 'New Subject',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.subject', 'New Subject')
            ->assertJsonPath('data.name', 'to_update'); // unchanged
    }

    public function test_update_template_requires_permission(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'no_access',
            'subject'    => 'S',
            'body'       => 'B',
            'variables'  => [],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->withToken($this->regularToken)
            ->putJson("/api/v1/notification-templates/{$template->id}", [
                'subject' => 'Changed',
            ]);

        $response->assertStatus(403);
    }

    public function test_delete_template_succeeds_when_no_notifications(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'to_delete',
            'subject'    => 'S',
            'body'       => 'B',
            'variables'  => [],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/notification-templates/{$template->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notification_templates', ['id' => $template->id]);
    }

    public function test_delete_template_fails_when_notifications_exist(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'has_notifications',
            'subject'    => 'S',
            'body'       => 'B',
            'variables'  => [],
            'created_by' => $this->adminUser->id,
        ]);

        Notification::create([
            'template_id'      => $template->id,
            'recipient_id'     => $this->regularUser->id,
            'subject_rendered' => 'Subject',
            'body_rendered'    => 'Body',
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/notification-templates/{$template->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('notification_templates', ['id' => $template->id]);
    }
}
