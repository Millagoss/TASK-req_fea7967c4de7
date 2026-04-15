<?php

namespace App\Services;

class FieldMaskingService
{
    /**
     * List of field names considered sensitive for log/export masking.
     */
    const SENSITIVE_FIELDS = [
        'password', 'password_hash', 'service_credential_hash',
        'ip_address', 'identifier',
    ];

    /**
     * Partial redaction: show first 2 and last 2 chars, mask middle.
     * For short strings (< 6 chars), mask entirely.
     */
    public static function mask(string $value): string
    {
        $len = mb_strlen($value);

        if ($len < 6) {
            return str_repeat('*', $len);
        }

        $first = mb_substr($value, 0, 2);
        $last = mb_substr($value, -2);
        $masked = str_repeat('*', $len - 4);

        return $first . $masked . $last;
    }

    /**
     * Mask sensitive fields in an array (e.g., log context).
     */
    public static function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::SENSITIVE_FIELDS, true)) {
                if (is_string($value)) {
                    $data[$key] = self::mask($value);
                } elseif (is_scalar($value)) {
                    $data[$key] = self::mask((string) $value);
                }
            } elseif (is_array($value)) {
                $data[$key] = self::maskArray($value);
            }
        }

        return $data;
    }
}
