<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ServiceAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceAccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ServiceAccountService();
    }

    public function test_create_returns_user_and_32_char_credential(): void
    {
        $result = $this->service->create('svc_bot', 'Service Bot');

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('credential', $result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertEquals(32, strlen($result['credential']));
    }

    public function test_created_user_has_is_service_account_true(): void
    {
        $result = $this->service->create('svc_bot', 'Service Bot');

        $this->assertTrue($result['user']->is_service_account);
        $this->assertTrue($result['user']->is_active);
        $this->assertEquals('svc_bot', $result['user']->username);
        $this->assertEquals('Service Bot', $result['user']->display_name);
    }

    public function test_credential_authenticates_the_service_account(): void
    {
        $result = $this->service->create('svc_bot', 'Service Bot');

        $this->assertTrue(
            Hash::check($result['credential'], $result['user']->service_credential_hash)
        );
    }

    public function test_rotate_generates_new_credential(): void
    {
        $created = $this->service->create('svc_bot', 'Service Bot');
        $oldCredential = $created['credential'];

        $rotated = $this->service->rotate($created['user']);

        $this->assertNotEquals($oldCredential, $rotated['credential']);
        $this->assertEquals(32, strlen($rotated['credential']));
        $this->assertNotNull($rotated['user']->service_credential_rotated_at);
    }

    public function test_old_credential_no_longer_works_after_rotation(): void
    {
        $created = $this->service->create('svc_bot', 'Service Bot');
        $oldCredential = $created['credential'];

        $rotated = $this->service->rotate($created['user']);

        // Old credential should NOT match
        $this->assertFalse(
            Hash::check($oldCredential, $rotated['user']->service_credential_hash)
        );

        // New credential should match
        $this->assertTrue(
            Hash::check($rotated['credential'], $rotated['user']->service_credential_hash)
        );
    }

    public function test_credential_contains_only_alphanumeric_chars(): void
    {
        $result = $this->service->create('svc_bot', 'Service Bot');

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{32}$/', $result['credential']);
    }
}
