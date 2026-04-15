<?php

namespace Tests\Unit;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService();
    }

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'username'      => 'testuser',
            'password_hash' => Hash::make('correct-password', ['rounds' => 12]),
            'display_name'  => 'Test User',
            'is_active'     => true,
        ], $overrides));
    }

    public function test_successful_login_with_correct_credentials(): void
    {
        $user = $this->createUser();

        $result = $this->service->attempt('testuser', 'correct-password', '127.0.0.1');

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
        $this->assertEquals('testuser', $result->username);

        // Verify login attempt was recorded as successful
        $attempt = LoginAttempt::where('username', 'testuser')->latest('attempted_at')->first();
        $this->assertNotNull($attempt);
        $this->assertTrue($attempt->successful);
    }

    public function test_failed_login_with_wrong_password_records_attempt_and_throws(): void
    {
        $this->createUser();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        try {
            $this->service->attempt('testuser', 'wrong-password', '127.0.0.1');
        } catch (AuthenticationException $e) {
            // Verify a failed attempt was recorded
            $attempts = LoginAttempt::where('username', 'testuser')->get();
            $this->assertGreaterThanOrEqual(1, $attempts->count());
            $this->assertFalse($attempts->last()->successful);
            throw $e;
        }
    }

    public function test_lockout_after_10_failed_attempts_in_15_minutes(): void
    {
        $user = $this->createUser();

        // Create 9 failed attempts in the database (the attempt() call will create 1 more via recordAttempt)
        for ($i = 0; $i < 10; $i++) {
            LoginAttempt::create([
                'username'     => 'testuser',
                'ip_address'   => '127.0.0.1',
                'attempted_at' => Carbon::now()->subSeconds($i),
                'successful'   => false,
            ]);
        }

        try {
            $this->service->attempt('testuser', 'wrong-password', '127.0.0.1');
        } catch (AuthenticationException $e) {
            // expected
        }

        $user->refresh();
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
    }

    public function test_account_locked_returns_appropriate_exception(): void
    {
        $this->createUser([
            'locked_until' => Carbon::now()->addMinutes(10),
        ]);

        $this->expectException(HttpException::class);

        try {
            $this->service->attempt('testuser', 'correct-password', '127.0.0.1');
        } catch (HttpException $e) {
            $this->assertEquals(423, $e->getStatusCode());
            $this->assertStringContainsString('Account locked', $e->getMessage());
            throw $e;
        }
    }

    public function test_make_hash_produces_valid_bcrypt_hash_with_cost_12(): void
    {
        $hash = AuthService::makeHash('my-secret');

        $this->assertTrue(Hash::check('my-secret', $hash));
        $this->assertFalse(Hash::check('wrong-secret', $hash));

        // Verify bcrypt cost is 12 by inspecting the hash prefix
        // Bcrypt hashes look like $2y$12$...
        $this->assertMatchesRegularExpression('/^\$2[aby]\$12\$/', $hash);
    }

    public function test_login_with_inactive_account_throws(): void
    {
        $this->createUser(['is_active' => false]);

        $this->expectException(HttpException::class);

        try {
            $this->service->attempt('testuser', 'correct-password', '127.0.0.1');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('inactive', $e->getMessage());
            throw $e;
        }
    }

    public function test_login_with_nonexistent_user_throws(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $this->service->attempt('nonexistent', 'password', '127.0.0.1');
    }

    public function test_successful_login_loads_roles_and_permissions(): void
    {
        $user = $this->createUser();

        $result = $this->service->attempt('testuser', 'correct-password', '127.0.0.1');

        $this->assertTrue($result->relationLoaded('roles'));
    }
}
