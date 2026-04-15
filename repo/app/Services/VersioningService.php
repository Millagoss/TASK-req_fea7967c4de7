<?php

namespace App\Services;

class VersioningService
{
    /**
     * Bump the version based on the specified component.
     *
     * @param  string  $bump  'major', 'minor', or 'patch'
     * @param  array   $version  [major, minor, patch]
     * @return array  [major, minor, patch]
     */
    public static function bumpVersion(string $bump, array $version): array
    {
        return match ($bump) {
            'major' => self::incrementMajor($version),
            'minor' => self::incrementMinor($version),
            'patch' => self::incrementPatch($version),
            default => $version,
        };
    }

    /**
     * Increment the major version.
     * Resets minor and patch to 0.
     */
    public static function incrementMajor(array $version): array
    {
        return [
            $version[0] + 1,
            0,
            0,
        ];
    }

    /**
     * Increment the minor version.
     * Resets patch to 0.
     */
    public static function incrementMinor(array $version): array
    {
        return [
            $version[0],
            $version[1] + 1,
            0,
        ];
    }

    /**
     * Increment the patch version.
     */
    public static function incrementPatch(array $version): array
    {
        return [
            $version[0],
            $version[1],
            $version[2] + 1,
        ];
    }
}
