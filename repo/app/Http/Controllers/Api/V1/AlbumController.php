<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageAlbumSongsRequest;
use App\Http\Requests\StoreAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use App\Http\Requests\UploadCoverArtRequest;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\SongResource;
use App\Models\Album;
use App\Models\Song;
use App\Services\CoverArtService;
use App\Services\VersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AlbumController extends Controller
{
    /**
     * GET /api/v1/albums
     * Search, filter, and paginate albums.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Album::query();

        // Full-text search
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($q_builder) use ($q) {
                $q_builder->where('title', 'like', "%{$q}%")
                    ->orWhere('artist', 'like', "%{$q}%");
            });
        }

        // Filter by artist
        if ($request->filled('artist')) {
            $query->where('artist', $request->input('artist'));
        }

        // Filter by publish state
        if ($request->filled('publish_state')) {
            $query->where('publish_state', $request->input('publish_state'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'updated_at');
        $sortDir = $request->input('sort_dir', 'desc');

        if (!in_array($sortBy, ['title', 'artist', 'updated_at'])) {
            $sortBy = 'updated_at';
        }
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $query->orderBy($sortBy, $sortDir)->orderBy('id', 'asc');

        // Pagination
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $albums = $query->paginate($perPage);

        return response()->json([
            'data' => AlbumResource::collection($albums->items()),
            'meta' => [
                'current_page' => $albums->currentPage(),
                'last_page'    => $albums->lastPage(),
                'per_page'     => $albums->perPage(),
                'total'        => $albums->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/albums
     * Create a new album.
     */
    public function store(StoreAlbumRequest $request): JsonResponse
    {
        $album = Album::create([
            'title'         => $request->input('title'),
            'artist'        => $request->input('artist'),
            'publish_state' => 'draft',
            'version_major' => 1,
            'version_minor' => 0,
            'version_patch' => 0,
            'created_by'    => $request->user()->id,
        ]);

        return response()->json(new AlbumResource($album), 201);
    }

    /**
     * GET /api/v1/albums/{id}
     * Show a single album.
     */
    public function show(int $id): JsonResponse
    {
        $album = Album::findOrFail($id);

        return response()->json(new AlbumResource($album));
    }

    /**
     * PUT /api/v1/albums/{id}
     * Update album metadata and bump version if published.
     */
    public function update(UpdateAlbumRequest $request, int $id): JsonResponse
    {
        $album = Album::findOrFail($id);

        if ($album->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only modify your own albums.'], 403);
        }

        $data = array_filter([
            'title'  => $request->input('title'),
            'artist' => $request->input('artist'),
        ], fn ($v) => $v !== null);

        // If the album is published and we're changing metadata, bump patch version
        if ($album->publish_state === 'published' && !empty($data)) {
            // Check if artist is changing (major bump)
            if ($request->filled('artist') && $request->input('artist') !== $album->artist) {
                $newVersion = VersioningService::incrementMajor([
                    $album->version_major,
                    $album->version_minor,
                    $album->version_patch,
                ]);
            } else {
                // Otherwise just patch bump
                $newVersion = VersioningService::incrementPatch([
                    $album->version_major,
                    $album->version_minor,
                    $album->version_patch,
                ]);
            }
            $data['version_major'] = $newVersion[0];
            $data['version_minor'] = $newVersion[1];
            $data['version_patch'] = $newVersion[2];
        }

        $album->update($data);

        return response()->json(new AlbumResource($album));
    }

    /**
     * DELETE /api/v1/albums/{id}
     * Delete an album (only if draft).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $album = Album::findOrFail($id);

        if ($album->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only delete your own albums.'], 403);
        }

        if ($album->publish_state !== 'draft') {
            throw ValidationException::withMessages([
                'id' => 'Only draft albums can be deleted.',
            ]);
        }

        // Delete cover art if present
        if ($album->cover_art_path) {
            CoverArtService::delete($album->cover_art_path);
        }

        $album->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/albums/{id}/publish
     * Publish an album (draft/unpublished → published).
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $album = Album::findOrFail($id);

        if ($album->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only publish your own albums.'], 403);
        }

        if (!in_array($album->publish_state, ['draft', 'unpublished'])) {
            throw ValidationException::withMessages([
                'id' => 'Album cannot be published from its current state.',
            ]);
        }

        $album->update(['publish_state' => 'published']);

        return response()->json(new AlbumResource($album));
    }

    /**
     * POST /api/v1/albums/{id}/unpublish
     * Unpublish an album (published/unpublished → unpublished).
     */
    public function unpublish(Request $request, int $id): JsonResponse
    {
        $album = Album::findOrFail($id);

        if ($album->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only unpublish your own albums.'], 403);
        }

        if ($album->publish_state === 'draft') {
            throw ValidationException::withMessages([
                'id' => 'Draft albums cannot be unpublished.',
            ]);
        }

        $album->update(['publish_state' => 'unpublished']);

        return response()->json(new AlbumResource($album));
    }

    /**
     * POST /api/v1/albums/{id}/version
     * Manually bump the version.
     */
    public function bumpVersion(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'bump' => ['required', 'in:major,minor,patch'],
        ]);

        $album = Album::findOrFail($id);

        if ($album->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only bump version of your own albums.'], 403);
        }

        $newVersion = VersioningService::bumpVersion(
            $request->input('bump'),
            [
                $album->version_major,
                $album->version_minor,
                $album->version_patch,
            ]
        );

        $album->update([
            'version_major' => $newVersion[0],
            'version_minor' => $newVersion[1],
            'version_patch' => $newVersion[2],
        ]);

        return response()->json(new AlbumResource($album));
    }

    /**
     * POST /api/v1/albums/{id}/cover-art
     * Upload cover art for an album.
     */
    public function uploadCoverArt(UploadCoverArtRequest $request, int $id): JsonResponse
    {
        $album = Album::findOrFail($id);

        if ($album->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only upload cover art for your own albums.'], 403);
        }

        // Delete old cover art if present
        if ($album->cover_art_path) {
            CoverArtService::delete($album->cover_art_path);
        }

        // Store new cover art
        $result = CoverArtService::store($request->file('cover_art'), 'albums', $album->id);

        $album->update([
            'cover_art_path'   => $result['path'],
            'cover_art_sha256' => $result['sha256'],
        ]);

        return response()->json(new AlbumResource($album));
    }

    /**
     * GET /api/v1/albums/{id}/songs
     * List songs in an album, ordered by position.
     */
    public function showSongs(int $id): JsonResponse
    {
        $album = Album::with('songs')->findOrFail($id);

        return response()->json([
            'data' => SongResource::collection($album->songs),
        ]);
    }

    /**
     * POST /api/v1/albums/{id}/songs
     * Add a song to an album.
     */
    public function addSong(ManageAlbumSongsRequest $request, int $id): JsonResponse
    {
        $album = Album::findOrFail($id);
        $songId = $request->input('song_id');
        $position = $request->input('position');

        // Verify song exists
        Song::findOrFail($songId);

        // Check if song already in album
        if ($album->songs()->where('song_id', $songId)->exists()) {
            throw ValidationException::withMessages([
                'song_id' => 'This song is already in the album.',
            ]);
        }

        // Add song to album
        $album->songs()->attach($songId, ['position' => $position]);

        // If album is published, bump minor version
        if ($album->publish_state === 'published') {
            $newVersion = VersioningService::incrementMinor([
                $album->version_major,
                $album->version_minor,
                $album->version_patch,
            ]);
            $album->update([
                'version_major' => $newVersion[0],
                'version_minor' => $newVersion[1],
                'version_patch' => $newVersion[2],
            ]);
        }

        $album->load('songs');

        return response()->json([
            'data' => new AlbumResource($album),
            'songs' => SongResource::collection($album->songs),
        ], 201);
    }

    /**
     * DELETE /api/v1/albums/{id}/songs/{songId}
     * Remove a song from an album.
     */
    public function removeSong(int $id, int $songId): JsonResponse
    {
        $album = Album::findOrFail($id);

        // Verify song is in album
        if (!$album->songs()->where('song_id', $songId)->exists()) {
            throw ValidationException::withMessages([
                'song_id' => 'This song is not in the album.',
            ]);
        }

        // Remove song from album
        $album->songs()->detach($songId);

        // If album is published, bump minor version
        if ($album->publish_state === 'published') {
            $newVersion = VersioningService::incrementMinor([
                $album->version_major,
                $album->version_minor,
                $album->version_patch,
            ]);
            $album->update([
                'version_major' => $newVersion[0],
                'version_minor' => $newVersion[1],
                'version_patch' => $newVersion[2],
            ]);
        }

        $album->load('songs');

        return response()->json([
            'data' => new AlbumResource($album),
            'songs' => SongResource::collection($album->songs),
        ]);
    }
}
