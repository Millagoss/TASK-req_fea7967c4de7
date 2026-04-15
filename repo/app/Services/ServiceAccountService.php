<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ServiceAccountService
{
    private const CREDENTIAL_LENGTH = 32;
    private const BCRYPT_COST       = 12;
    private const CHARSET           = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * Create a new service account.
     *
     * Returns the newly created User and the plaintext credential (shown once).
     *
     * @return array{user: User, credential: string}
     */
    public function create(string $username, string $displayName): array
    {
        $plainCredential = $this->generateCredential();

        $user = User::create([
            'username'                     => $username,
            'display_name'                 => $displayName,
            'password_hash'                => Hash::make(Str::random(40), ['rounds' => self::BCRYPT_COST]),
            'is_service_account'           => true,
            'service_credential_hash'      => Hash::make($plainCredential, ['rounds' => self::BCRYPT_COST]),
            'service_credential_rotated_at' => Carbon::now(),
            'is_active'                    => true,
        ]);

        return [
            'user'       => $user,
            'credential' => $plainCredential,
        ];
    }

    /**
     * Rotate the credential for an existing service account.
     *
     * @return array{user: User, credential: string}
     */
    public function rotate(User $user): array
    {
        $plainCredential = $this->generateCredential();

        $user->service_credential_hash      = Hash::make($plainCredential, ['rounds' => self::BCRYPT_COST]);
        $user->service_credential_rotated_at = Carbon::now();
        $user->save();

        // Revoke all existing tokens so old credential sessions are invalidated.
        $user->tokens()->update(['revoked_at' => Carbon::now()]);

        return [
            'user'       => $user,
            'credential' => $plainCredential,
        ];
    }

    private function generateCredential(): string
    {
        $charset = self::CHARSET;
        $length  = strlen($charset);
        $result  = '';

        for ($i = 0; $i < self::CREDENTIAL_LENGTH; $i++) {
            $result .= $charset[random_int(0, $length - 1)];
        }

        return $result;
    }
}
