<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    private const MAX_ATTEMPTS    = 10;
    private const WINDOW_MINUTES  = 15;
    private const LOCKOUT_MINUTES = 15;
    private const BCRYPT_COST     = 12;

    /**
     * Attempt to authenticate a user.
     *
     * Returns the User on success, or throws an exception on failure.
     *
     * @throws \Illuminate\Auth\AuthenticationException
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function attempt(string $username, string $password, string $ipAddress): User
    {
        $user = User::where('username', $username)->with('roles.permissions')->first();

        // Record the attempt before any early returns so we always track it.
        $this->recordAttempt($username, $ipAddress, false);

        if ($user === null) {
            throw new \Illuminate\Auth\AuthenticationException('Invalid credentials.');
        }

        if (! $user->is_active) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'Account is inactive.');
        }

        if ($user->isLocked()) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                423,
                'Account locked. Try again after '.$user->locked_until->toIso8601String().'.'
            );
        }

        $valid = $this->validateCredentials($user, $password);

        if (! $valid) {
            $this->handleFailedAttempt($user, $username, $ipAddress);
            throw new \Illuminate\Auth\AuthenticationException('Invalid credentials.');
        }

        // Successful login — update the attempt record to successful.
        LoginAttempt::where('username', $username)
            ->where('ip_address', $ipAddress)
            ->latest('attempted_at')
            ->first()
            ?->update(['successful' => true]);

        return $user;
    }

    private function validateCredentials(User $user, string $password): bool
    {
        if ($user->is_service_account) {
            return Hash::check($password, $user->service_credential_hash ?? '');
        }

        if (config('app.sso_enabled', (bool) env('SSO_ENABLED', false))) {
            return $this->validateViaSso($user->username, $password);
        }

        return Hash::check($password, $user->getAuthPassword());
    }

    private function validateViaSso(string $username, string $password): bool
    {
        $host   = env('LDAP_HOST', '');
        $port   = (int) env('LDAP_PORT', 389);
        $userDn = env('LDAP_USER_DN', '');
        $filter = str_replace('{username}', ldap_escape($username, '', LDAP_ESCAPE_FILTER), env('LDAP_USER_FILTER', '(uid={username})'));

        if (empty($host)) {
            Log::warning('SSO enabled but LDAP_HOST not configured; falling back to local auth.');
            return false;
        }

        $conn = @ldap_connect($host, $port);
        if (! $conn) {
            Log::error('LDAP connection failed to '.$host.':'.$port);
            return false;
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        // Bind with service account to find user DN.
        $serviceUser = env('LDAP_USERNAME', '');
        $servicePass = env('LDAP_PASSWORD', '');

        if (! @ldap_bind($conn, $serviceUser, $servicePass)) {
            Log::error('LDAP service bind failed.');
            ldap_close($conn);
            return false;
        }

        $search = @ldap_search($conn, $userDn, $filter, ['dn']);
        if (! $search) {
            ldap_close($conn);
            return false;
        }

        $entries = ldap_get_entries($conn, $search);
        if ($entries['count'] === 0) {
            ldap_close($conn);
            return false;
        }

        $userDistinguishedName = $entries[0]['dn'];

        // Bind as the found user to validate password.
        $result = @ldap_bind($conn, $userDistinguishedName, $password);
        ldap_close($conn);

        return $result;
    }

    private function recordAttempt(string $username, string $ipAddress, bool $successful): void
    {
        LoginAttempt::create([
            'username'     => $username,
            'ip_address'   => $ipAddress,
            'attempted_at' => Carbon::now(),
            'successful'   => $successful,
        ]);
    }

    private function handleFailedAttempt(User $user, string $username, string $ipAddress): void
    {
        $windowStart   = Carbon::now()->subMinutes(self::WINDOW_MINUTES);
        $recentFailed  = LoginAttempt::where('username', $username)
            ->where('attempted_at', '>=', $windowStart)
            ->where('successful', false)
            ->count();

        if ($recentFailed >= self::MAX_ATTEMPTS) {
            $user->locked_until = Carbon::now()->addMinutes(self::LOCKOUT_MINUTES);
            $user->save();
            Log::warning("User {$username} locked out after {$recentFailed} failed attempts.");
        }
    }

    public static function makeHash(string $plaintext): string
    {
        return Hash::make($plaintext, ['rounds' => self::BCRYPT_COST]);
    }

    /**
     * Find-or-create a local user record for an SSO-authenticated user.
     */
    public function findOrCreateSsoUser(string $username, string $displayName = ''): User
    {
        return User::firstOrCreate(
            ['username' => $username],
            [
                'display_name' => $displayName ?: $username,
                'password_hash' => self::makeHash(bin2hex(random_bytes(16))),
                'is_active'    => true,
            ]
        );
    }
}
