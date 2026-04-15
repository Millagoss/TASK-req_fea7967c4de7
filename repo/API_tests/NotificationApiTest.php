<?php

namespace Tests\Api;

use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $recipientUser;
    protected string $adminToken;
    protected string $recipientToken;
    protected NotificationTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $usersList = Permission::firstOrCreate(['name' => 'users.list']);

        // Create admin role
        $adminRole = Role::create(['name' => 'notif_admin', 'description' => 'Notif Admin']);
        $adminRole->permissions()->sync([$usersList->id]);

        // Create admin user
        $this->adminUser = User::create([
            'username'      => 'notif_admin_user',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Notif Admin',
            'is_active'     => true,
        ]);
        $this->adminUser->roles()->attach($adminRole->id);
        $this->adminToken = $this->adminUser->createToken('test')->plainTextToken;

        // Create recipient user
        $this->recipientUser = User::create([
            'username'      => 'notif_recipient',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Recipient',
            'is_active'     => true,
        ]);
        $this->recipientToken = $this->recipientUser->createToken('test')->plainTextToken;

        // Create a template
        $this->template = NotificationTemplate::create([
            'name'       => 'test_notification',
            'subject'    => 'Hello {{username}}',
            'body'       => 'Message for {{username}}',
            'variables'  => ['username'],
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_send_notification_requires_permission(): void
    {
        $response = $this->withToken($this->recipientToken)
            ->postJson('/api/v1/notifications/send', [
                'template_id'   => $this->template->id,
                'recipient_ids' => [$this->recipientUser->id],
                'variables'     => ['username' => 'Test'],
            ]);

        $response->assertStatus(403);
    }

    public function test_send_notification_succeeds(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/notifications/send', [
                'template_id'   => $this->template->id,
                'recipient_ids' => [$this->recipientUser->id],
                'variables'     => ['username' => 'Alice'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('sent', 1)
            ->assertJsonPath('skipped', 0);

        $this->assertDatabaseHas('notifications', [
            'recipient_id'     => $this->recipientUser->id,
            'subject_rendered' => 'Hello Alice',
        ]);
    }

    public function test_send_notification_validates_input(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/notifications/send', []);

        $response->assertStatus(422);
    }

    public function test_send_bulk_notification_returns_batch_id(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/notifications/send-bulk', [
                'template_id'   => $this->template->id,
                'recipient_ids' => [$this->recipientUser->id, $this->adminUser->id],
                'variables'     => ['username' => 'Bulk'],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['sent', 'skipped', 'skipped_reasons', 'batch_id']);

        $batchId = $response->json('batch_id');
        $this->assertNotEmpty($batchId);
        $this->assertEquals(2, Notification::where('batch_id', $batchId)->count());
    }

    public function test_list_notifications_returns_own_only(): void
    {
        // Create notification for recipient
        Notification::create([
            'template_id'      => $this->template->id,
            'recipient_id'     => $this->recipientUser->id,
            'subject_rendered' => 'For Recipient',
            'body_rendered'    => 'Body',
        ]);

        // Create notification for admin
        Notification::create([
            'template_id'      => $this->template->id,
            'recipient_id'     => $this->adminUser->id,
            'subject_rendered' => 'For Admin',
            'body_rendered'    => 'Body',
        ]);

        $response = $this->withToken($this->recipientToken)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('For Recipient', $data[0]['subject_rendered']);
    }

    public function test_list_notifications_filters_by_read_status(): void
    {
        Notification::create([
            'template_id'      => $this->template->id,
            'recipient_id'     => $this->recipientUser->id,
            'subject_rendered' => 'Unread',
            'body_rendered'    => 'Body',
            'read_at'          => null,
        ]);

        Notification::create([
            'template_id'      => $this->template->id,
            'recipient_id'     => $this->recipientUser->id,
            'subject_rendered' => 'Read',
            'body_rendered'    => 'Body',
            'read_at'          => now(),
        ]);

        // Only unread
        $response = $this->withToken($this->recipientToken)
            ->getJson('/api/v1/notifications?read=false');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Unread', $response->json('data.0.subject_rendered'));

        // Only read
        $response = $this->withToken($this->recipientToken)
            ->getJson('/api/v1/notifications?read=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Read', $response->json('data.0.subject_rendered'));
    }

    public function test_mark_notification_as_read(): void
    {
        $notification = Notification::create([
            'template_id'      => $this->template->id,
            'recipient_id'     => $this->recipientUser->id,
            'subject_rendered' => 'To Read',
            'body_rendered'    => 'Body',
        ]);

        $response = $this->withToken($this->recipientToken)
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_read_is_idempotent(): void
    {
        $notification = Notification::create([
            'template_id'      => $this->template->id,
            'recipient_id'     => $this->recipientUser->id,
            'subject_rendered' => 'Idempotent',
            'body_rendered'    => 'Body',
            'read_at'          => now()->subMinutes(5),
        ]);

        $originalReadAt = $notification->read_at->toIso8601String();

        $response = $this->withToken($this->recipientToken)
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $notification->refresh();
        $this->assertEquals($originalReadAt, $notification->read_at->toIso8601String());
    }

    public function test_cannot_mark_other_users_notification_as_read(): void
    {
        $notification = Notification::create([
            'template_id'      => $this->template->id,
            'recipient_id'     => $this->adminUser->id,
            'subject_rendered' => 'Not yours',
            'body_rendered'    => 'Body',
        ]);

        $response = $this->withToken($this->recipientToken)
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(404);
    }

    public function test_read_all_marks_all_unread_as_read(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'template_id'      => $this->template->id,
                'recipient_id'     => $this->recipientUser->id,
                'subject_rendered' => "Unread $i",
                'body_rendered'    => 'Body',
            ]);
        }

        // One already read
        Notification::create([
            'template_id'      => $this->template->id,
            'recipient_id'     => $this->recipientUser->id,
            'subject_rendered' => 'Already Read',
            'body_rendered'    => 'Body',
            'read_at'          => now(),
        ]);

        $response = $this->withToken($this->recipientToken)
            ->postJson('/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('updated', 3);
    }

    public function test_unread_count(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'template_id'      => $this->template->id,
                'recipient_id'     => $this->recipientUser->id,
                'subject_rendered' => "Notif $i",
                'body_rendered'    => 'Body',
                'read_at'          => $i < 3 ? null : now(),
            ]);
        }

        $response = $this->withToken($this->recipientToken)
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('unread_count', 3);
    }

    public function test_list_notifications_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/notifications');
        $response->assertStatus(401);
    }
}
