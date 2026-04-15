<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CoverArtService
{
    /**
     * Store a cover art file and return path and SHA-256 hash.
     *
     * @param  UploadedFile  $file
     * @param  string  $entityType  'songs' or 'albums'
     * @param  int  $entityId
     * @return array  ['path' => ..., 'sha256' => ...]
     */
    public static function store(UploadedFile $file, string $entityType, int $entityId): array
    {
        self::validate($file);

        $extension = $file->getClientOriginalExtension();
        $contents = file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $contents);

        $path = "cover-art/{$entityType}/{$entityId}/{$sha256}.{$extension}";
        Storage::disk('local')->put($path, $contents);

        return [
            'path'   => $path,
            'sha256' => $sha256,
        ];
    }

    /**
     * Delete a cover art file.
     *
     * @param  string  $coverArtPath
     * @return bool
     */
    public static function delete(string $coverArtPath): bool
    {
        if (!$coverArtPath) {
            return false;
        }

        return Storage::disk('local')->delete($coverArtPath);
    }

    /**
     * Validate cover art file.
     *
     * @param  UploadedFile  $file
     * @return void
     * @throws ValidationException
     */
    public static function validate(UploadedFile $file): void
    {
        $maxSizeBytes = 5 * 1024 * 1024;

        if ($file->getSize() > $maxSizeBytes) {
            throw ValidationException::withMessages([
                'cover_art' => 'Cover art file must not exceed 5MB.',
            ]);
        }

        $mimeType = $file->getMimeType();
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mimeType, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'cover_art' => 'Cover art must be a JPEG, PNG, or WebP image.',
            ]);
        }
    }
}
