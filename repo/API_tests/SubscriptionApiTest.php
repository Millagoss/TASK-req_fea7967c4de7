<?php

namespace Tests\Api;

use App\Models\NotificationSubscription;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected NotificationTemplate $template1;
    protected NotificationTemplate $template2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username'      => 'sub_tester',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Sub Tester',
            'is_active'     => true,
        ]);
        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->template1 = NotificationTemplate::create([
            'name'       => 'template_alpha',
            'subject'    => 'Alpha Subject',
            'body'       => 'Alpha Body',
            'variables'  => [],
            'created_by' => $this->user->id,
        ]);

        $this->template2 = NotificationTemplate::create([
            'name'       => 'template_beta',
            'subject'    => 'Beta Subject',
            'body'       => 'Beta Body',
            'variables'  => [],
            'created_by' => $this->user->id,
        ]);
    }

    public function test_list_subscriptions_defaults_to_subscribed(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/subscriptions');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        // All should default to subscribed
        foreach ($data as $item) {
            $this->assertTrue($item['is_subscribed']);
        }
    }

    public function test_list_subscriptions_reflects_explicit_unsubscribe(): void
    {
        NotificationSubscription::create([
            'user_id'       => $this->user->id,
            'template_id'   => $this->template1->id,
            'is_subscribed' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/subscriptions');

        $response->assertStatus(200);
        $data = $response->json('data');

        $alpha = collect($data)->firstWhere('template_id', $this->template1->id);
        $beta = collect($data)->firstWhere('template_id', $this->template2->id);

        $this->assertFalse($alpha['is_subscribed']);
        $this->assertTrue($beta['is_subscribed']);
    }

    public function test_update_subscriptions(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v1/subscriptions', [
                'subscriptions' => [
                    ['template_id' => $this->template1->id, 'is_subscribed' => false],
                    ['template_id' => $this->template2->id, 'is_subscribed' => true],
                ],
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $alpha = collect($data)->firstWhere('template_id', $this->template1->id);
        $this->assertFalse($alpha['is_subscribed']);

        // Verify database
        $this->assertDatabaseHas('notification_subscriptions', [
            'user_id'       => $this->user->id,
            'template_id'   => $this->template1->id,
            'is_subscribed' => false,
        ]);
    }

    public function test_update_subscriptions_upserts_existing(): void
    {
        // Create initial subscription
        NotificationSubscription::create([
            'user_id'       => $this->user->id,
            'template_id'   => $this->template1->id,
            'is_subscribed' => false,
        ]);

        // Update it to subscribed
        $response = $this->withToken($this->token)
            ->putJson('/api/v1/subscriptions', [
                'subscriptions' => [
                    ['template_id' => $this->template1->id, 'is_subscribed' => true],
                ],
            ]);

        $response->assertStatus(200);

        // Should have only one record (upsert, not duplicate)
        $count = NotificationSubscription::where('user_id', $this->user->id)
            ->where('template_id', $this->template1->id)
            ->count();
        $this->assertEquals(1, $count);

        $this->assertDatabaseHas('notification_subscriptions', [
            'user_id'       => $this->user->id,
            'template_id'   => $this->template1->id,
            'is_subscribed' => true,
        ]);
    }

    public function test_update_subscriptions_validates_input(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v1/subscriptions', []);

        $response->assertStatus(422);
    }

    public function test_subscriptions_require_auth(): void
    {
        $response = $this->getJson('/api/v1/subscriptions');
        $response->assertStatus(401);
    }
}
