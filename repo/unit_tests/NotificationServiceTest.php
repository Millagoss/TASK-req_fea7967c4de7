<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\NotificationSubscription;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $service;
    protected User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService();
        $this->creator = User::create([
            'username'      => 'admin_tester',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Admin Tester',
            'is_active'     => true,
        ]);
    }

    private function createTemplate(array $overrides = []): NotificationTemplate
    {
        return NotificationTemplate::create(array_merge([
            'name'       => 'test_template_' . uniqid(),
            'subject'    => 'Hello {{username}}',
            'body'       => 'Dear {{username}}, your song {{song_title}} is ready.',
            'variables'  => ['username', 'song_title'],
            'created_by' => $this->creator->id,
        ], $overrides));
    }

    private function createUser(string $suffix = ''): User
    {
        return User::create([
            'username'      => 'user_' . uniqid() . $suffix,
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Test User ' . $suffix,
            'is_active'     => true,
        ]);
    }

    public function test_render_replaces_placeholders(): void
    {
        $result = $this->service->render(
            'Hello {{username}}, welcome to {{app_name}}!',
            ['username' => 'John', 'app_name' => 'Meridian']
        );

        $this->assertEquals('Hello John, welcome to Meridian!', $result);
    }

    public function test_render_preserves_unknown_placeholders(): void
    {
        $result = $this->service->render(
            'Hello {{username}}, your code is {{code}}',
            ['username' => 'John']
        );

        $this->assertEquals('Hello John, your code is {{code}}', $result);
    }

    public function test_send_creates_notifications(): void
    {
        $template = $this->createTemplate();
        $user1 = $this->createUser('1');
        $user2 = $this->createUser('2');

        $result = $this->service->send(
            $template->id,
            [$user1->id, $user2->id],
            ['username' => 'TestUser', 'song_title' => 'My Song']
        );

        $this->assertEquals(2, $result['sent']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEmpty($result['skipped_reasons']);
        $this->assertEquals(2, Notification::count());
    }

    public function test_send_renders_subject_and_body(): void
    {
        $template = $this->createTemplate();
        $user = $this->createUser();

        $this->service->send(
            $template->id,
            [$user->id],
            ['username' => 'Alice', 'song_title' => 'Moonlight']
        );

        $notification = Notification::first();
        $this->assertEquals('Hello Alice', $notification->subject_rendered);
        $this->assertEquals('Dear Alice, your song Moonlight is ready.', $notification->body_rendered);
    }

    public function test_send_throws_on_missing_variables(): void
    {
        $template = $this->createTemplate();
        $user = $this->createUser();

        $this->expectException(ValidationException::class);

        $this->service->send(
            $template->id,
            [$user->id],
            ['username' => 'Alice'] // missing song_title
        );
    }

    public function test_send_skips_unsubscribed_users(): void
    {
        $template = $this->createTemplate();
        $user = $this->createUser();

        NotificationSubscription::create([
            'user_id'       => $user->id,
            'template_id'   => $template->id,
            'is_subscribed' => false,
        ]);

        $result = $this->service->send(
            $template->id,
            [$user->id],
            ['username' => 'Bob', 'song_title' => 'Test']
        );

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals('unsubscribed', $result['skipped_reasons'][0]['reason']);
    }

    public function test_send_does_not_skip_subscribed_users(): void
    {
        $template = $this->createTemplate();
        $user = $this->createUser();

        // Explicitly subscribed
        NotificationSubscription::create([
            'user_id'       => $user->id,
            'template_id'   => $template->id,
            'is_subscribed' => true,
        ]);

        $result = $this->service->send(
            $template->id,
            [$user->id],
            ['username' => 'Bob', 'song_title' => 'Test']
        );

        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(0, $result['skipped']);
    }

    public function test_send_enforces_rate_limit(): void
    {
        $template = $this->createTemplate();
        $user = $this->createUser();

        $variables = ['username' => 'Rate', 'song_title' => 'Limited'];

        // Send 3 notifications (the limit)
        for ($i = 0; $i < NotificationService::RATE_LIMIT_PER_HOUR; $i++) {
            $this->service->send($template->id, [$user->id], $variables);
        }

        // 4th should be rate limited
        $result = $this->service->send($template->id, [$user->id], $variables);

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals('rate_limited', $result['skipped_reasons'][0]['reason']);
    }

    public function test_send_with_batch_id(): void
    {
        $template = $this->createTemplate();
        $user = $this->createUser();
        $batchId = '550e8400-e29b-41d4-a716-446655440000';

        $result = $this->service->send(
            $template->id,
            [$user->id],
            ['username' => 'Batch', 'song_title' => 'Test'],
            $batchId
        );

        $this->assertEquals(1, $result['sent']);
        $notification = Notification::first();
        $this->assertEquals($batchId, $notification->batch_id);
    }

    public function test_send_stores_variables_used(): void
    {
        $template = $this->createTemplate();
        $user = $this->createUser();

        $variables = ['username' => 'Vars', 'song_title' => 'Test Song'];
        $this->service->send($template->id, [$user->id], $variables);

        $notification = Notification::first();
        $this->assertEquals($variables, $notification->variables_used);
    }
}
