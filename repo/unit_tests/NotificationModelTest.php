<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\NotificationSubscription;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'username'      => 'model_tester',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Model Tester',
            'is_active'     => true,
        ]);
    }

    public function test_notification_template_casts_variables_to_array(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'cast_test',
            'subject'    => 'Test',
            'body'       => 'Test body',
            'variables'  => ['var1', 'var2'],
            'created_by' => $this->user->id,
        ]);

        $template->refresh();
        $this->assertIsArray($template->variables);
        $this->assertEquals(['var1', 'var2'], $template->variables);
    }

    public function test_notification_template_belongs_to_creator(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'relation_test',
            'subject'    => 'Test',
            'body'       => 'Test body',
            'variables'  => [],
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $template->creator);
        $this->assertEquals($this->user->id, $template->creator->id);
    }

    public function test_notification_belongs_to_template(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'notify_relation',
            'subject'    => 'Test',
            'body'       => 'Test body',
            'variables'  => [],
            'created_by' => $this->user->id,
        ]);

        $notification = Notification::create([
            'template_id'      => $template->id,
            'recipient_id'     => $this->user->id,
            'subject_rendered' => 'Rendered Subject',
            'body_rendered'    => 'Rendered Body',
        ]);

        $this->assertInstanceOf(NotificationTemplate::class, $notification->template);
        $this->assertEquals($template->id, $notification->template->id);
    }

    public function test_notification_belongs_to_recipient(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'recipient_test',
            'subject'    => 'Test',
            'body'       => 'Test body',
            'variables'  => [],
            'created_by' => $this->user->id,
        ]);

        $notification = Notification::create([
            'template_id'      => $template->id,
            'recipient_id'     => $this->user->id,
            'subject_rendered' => 'Subject',
            'body_rendered'    => 'Body',
        ]);

        $this->assertInstanceOf(User::class, $notification->recipient);
        $this->assertEquals($this->user->id, $notification->recipient->id);
    }

    public function test_notification_casts_variables_used_to_array(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'vars_cast',
            'subject'    => 'Test',
            'body'       => 'Test body',
            'variables'  => [],
            'created_by' => $this->user->id,
        ]);

        $notification = Notification::create([
            'template_id'      => $template->id,
            'recipient_id'     => $this->user->id,
            'subject_rendered' => 'Subject',
            'body_rendered'    => 'Body',
            'variables_used'   => ['key' => 'value'],
        ]);

        $notification->refresh();
        $this->assertIsArray($notification->variables_used);
        $this->assertEquals(['key' => 'value'], $notification->variables_used);
    }

    public function test_notification_casts_read_at_to_datetime(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'read_cast',
            'subject'    => 'Test',
            'body'       => 'Test body',
            'variables'  => [],
            'created_by' => $this->user->id,
        ]);

        $notification = Notification::create([
            'template_id'      => $template->id,
            'recipient_id'     => $this->user->id,
            'subject_rendered' => 'Subject',
            'body_rendered'    => 'Body',
            'read_at'          => now(),
        ]);

        $notification->refresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $notification->read_at);
    }

    public function test_notification_subscription_casts_is_subscribed_to_boolean(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'sub_cast',
            'subject'    => 'Test',
            'body'       => 'Test body',
            'variables'  => [],
            'created_by' => $this->user->id,
        ]);

        $sub = NotificationSubscription::create([
            'user_id'       => $this->user->id,
            'template_id'   => $template->id,
            'is_subscribed' => true,
        ]);

        $sub->refresh();
        $this->assertIsBool($sub->is_subscribed);
        $this->assertTrue($sub->is_subscribed);
    }

    public function test_notification_subscription_belongs_to_user_and_template(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'sub_relations',
            'subject'    => 'Test',
            'body'       => 'Test body',
            'variables'  => [],
            'created_by' => $this->user->id,
        ]);

        $sub = NotificationSubscription::create([
            'user_id'       => $this->user->id,
            'template_id'   => $template->id,
            'is_subscribed' => true,
        ]);

        $this->assertInstanceOf(User::class, $sub->user);
        $this->assertInstanceOf(NotificationTemplate::class, $sub->template);
    }

    public function test_notification_template_has_many_subscriptions(): void
    {
        $template = NotificationTemplate::create([
            'name'       => 'has_many_subs',
            'subject'    => 'Test',
            'body'       => 'Test body',
            'variables'  => [],
            'created_by' => $this->user->id,
        ]);

        $user2 = User::create([
            'username'      => 'sub_user2',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Sub User 2',
            'is_active'     => true,
        ]);

        NotificationSubscription::create([
            'user_id'       => $this->user->id,
            'template_id'   => $template->id,
            'is_subscribed' => true,
        ]);

        NotificationSubscription::create([
            'user_id'       => $user2->id,
            'template_id'   => $template->id,
            'is_subscribed' => false,
        ]);

        $this->assertCount(2, $template->subscriptions);
    }
}
