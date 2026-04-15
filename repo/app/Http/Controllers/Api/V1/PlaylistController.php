<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManagePlaylistSongsRequest;
use App\Http\Requests\StorePlaylistRequest;
use App\Http\Requests\UpdatePlaylistRequest;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\SongResource;
use App\Models\Playlist;
use App\Models\Song;
use App\Services\VersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlaylistController extends Controller
{
    /**
     * GET /api/v1/playlists
     * Search, filter, and paginate playlists.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Playlist::query();

        // Full-text search (includes artist via songs relationship)
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($q_builder) use ($q) {
                $q_builder->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('songs', function ($songQuery) use ($q) {
                        $songQuery->where('artist', 'like', "%{$q}%");
                    });
            });
        }

        // Filter by artist (via songs relationship)
        if ($request->filled('artist')) {
            $artist = $request->input('artist');
            $query->whereHas('songs', function ($songQuery) use ($artist) {
                $songQuery->where('artist', 'like', "%{$artist}%");
            });
        }

        // Filter by tags (via songs relationship)
        if ($request->filled('tags')) {
            $tags = array_map('trim', explode(',', $request->input('tags')));
            $query->whereHas('songs', function ($songQuery) use ($tags) {
                $songQuery->whereHas('tags', function ($tagQuery) use ($tags) {
                    $tagQuery->whereIn('tag', $tags);
                });
            });
        }

        // Filter by publish state
        if ($request->filled('publish_state')) {
            $query->where('publish_state', $request->input('publish_state'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'updated_at');
        $sortDir = $request->input('sort_dir', 'desc');

        if (!in_array($sortBy, ['title', 'updated_at'])) {
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

        $playlists = $query->paginate($perPage);

        return response()->json([
            'data' => PlaylistResource::collection($playlists->items()),
            'meta' => [
                'current_page' => $playlists->currentPage(),
                'last_page'    => $playlists->lastPage(),
                'per_page'     => $playlists->perPage(),
                'total'        => $playlists->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/playlists
     * Create a new playlist.
     */
    public function store(StorePlaylistRequest $request): JsonResponse
    {
        $playlist = Playlist::create([
            'title'         => $request->input('title'),
            'description'   => $request->input('description'),
            'publish_state' => 'draft',
            'version_major' => 1,
            'version_minor' => 0,
            'version_patch' => 0,
            'created_by'    => $request->user()->id,
        ]);

        return response()->json(new PlaylistResource($playlist), 201);
    }

    /**
     * GET /api/v1/playlists/{id}
     * Show a single playlist.
     */
    public function show(int $id): JsonResponse
    {
        $playlist = Playlist::findOrFail($id);

        return response()->json(new PlaylistResource($playlist));
    }

    /**
     * PUT /api/v1/playlists/{id}
     * Update playlist metadata and bump version if published.
     */
    public function update(UpdatePlaylistRequest $request, int $id): JsonResponse
    {
        $playlist = Playlist::findOrFail($id);

        if ($playlist->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only modify your own playlists.'], 403);
        }

        $data = array_filter([
            'title'       => $request->input('title'),
            'description' => $request->input('description'),
        ], fn ($v) => $v !== null);

        // If the playlist is published and we're changing metadata, bump patch version
        if ($playlist->publish_state === 'published' && !empty($data)) {
            $newVersion = VersioningService::incrementPatch([
                $playlist->version_major,
                $playlist->version_minor,
                $playlist->version_patch,
            ]);
            $data['version_major'] = $newVersion[0];
            $data['version_minor'] = $newVersion[1];
            $data['version_patch'] = $newVersion[2];
        }

        $playlist->update($data);

        return response()->json(new PlaylistResource($playlist));
    }

    /**
     * DELETE /api/v1/playlists/{id}
     * Delete a playlist (only if draft).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $playlist = Playlist::findOrFail($id);

        if ($playlist->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only delete your own playlists.'], 403);
        }

        if ($playlist->publish_state !== 'draft') {
            throw ValidationException::withMessages([
                'id' => 'Only draft playlists can be deleted.',
            ]);
        }

        $playlist->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/playlists/{id}/publish
     * Publish a playlist (draft/unpublished → published).
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $playlist = Playlist::findOrFail($id);

        if ($playlist->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only publish your own playlists.'], 403);
        }

        if (!in_array($playlist->publish_state, ['draft', 'unpublished'])) {
            throw ValidationException::withMessages([
                'id' => 'Playlist cannot be published from its current state.',
            ]);
        }

        $playlist->update(['publish_state' => 'published']);

        return response()->json(new PlaylistResource($playlist));
    }

    /**
     * POST /api/v1/playlists/{id}/unpublish
     * Unpublish a playlist (published/unpublished → unpublished).
     */
    public function unpublish(Request $request, int $id): JsonResponse
    {
        $playlist = Playlist::findOrFail($id);

        if ($playlist->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only unpublish your own playlists.'], 403);
        }

        if ($playlist->publish_state === 'draft') {
            throw ValidationException::withMessages([
                'id' => 'Draft playlists cannot be unpublished.',
            ]);
        }

        $playlist->update(['publish_state' => 'unpublished']);

        return response()->json(new PlaylistResource($playlist));
    }

    /**
     * POST /api/v1/playlists/{id}/version
     * Manually bump the version.
     */
    public function bumpVersion(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'bump' => ['required', 'in:major,minor,patch'],
        ]);

        $playlist = Playlist::findOrFail($id);

        if ($playlist->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only bump version of your own playlists.'], 403);
        }

        $newVersion = VersioningService::bumpVersion(
            $request->input('bump'),
            [
                $playlist->version_major,
                $playlist->version_minor,
                $playlist->version_patch,
            ]
        );

        $playlist->update([
            'version_major' => $newVersion[0],
            'version_minor' => $newVersion[1],
            'version_patch' => $newVersion[2],
        ]);

        return response()->json(new PlaylistResource($playlist));
    }

    /**
     * GET /api/v1/playlists/{id}/songs
     * List songs in a playlist, ordered by position.
     */
    public function showSongs(int $id): JsonResponse
    {
        $playlist = Playlist::with('songs')->findOrFail($id);

        return response()->json([
            'data' => SongResource::collection($playlist->songs),
        ]);
    }

    /**
     * POST /api/v1/playlists/{id}/songs
     * Add a song to a playlist.
     */
    public function addSong(ManagePlaylistSongsRequest $request, int $id): JsonResponse
    {
        $playlist = Playlist::findOrFail($id);
        $songId = $request->input('song_id');
        $position = $request->input('position');

        // Verify song exists
        Song::findOrFail($songId);

        // Check if song already in playlist
        if ($playlist->songs()->where('song_id', $songId)->exists()) {
            throw ValidationException::withMessages([
                'song_id' => 'This song is already in the playlist.',
            ]);
        }

        // Add song to playlist
        $playlist->songs()->attach($songId, ['position' => $position]);

        // If playlist is published, bump minor version
        if ($playlist->publish_state === 'published') {
            $newVersion = VersioningService::incrementMinor([
                $playlist->version_major,
                $playlist->version_minor,
                $playlist->version_patch,
            ]);
            $playlist->update([
                'version_major' => $newVersion[0],
                'version_minor' => $newVersion[1],
                'version_patch' => $newVersion[2],
            ]);
        }

        $playlist->load('songs');

        return response()->json([
            'data' => new PlaylistResource($playlist),
            'songs' => SongResource::collection($playlist->songs),
        ], 201);
    }

    /**
     * DELETE /api/v1/playlists/{id}/songs/{songId}
     * Remove a song from a playlist.
     */
    public function removeSong(int $id, int $songId): JsonResponse
    {
        $playlist = Playlist::findOrFail($id);

        // Verify song is in playlist
        if (!$playlist->songs()->where('song_id', $songId)->exists()) {
            throw ValidationException::withMessages([
                'song_id' => 'This song is not in the playlist.',
            ]);
        }

        // Remove song from playlist
        $playlist->songs()->detach($songId);

        // If playlist is published, bump minor version
        if ($playlist->publish_state === 'published') {
            $newVersion = VersioningService::incrementMinor([
                $playlist->version_major,
                $playlist->version_minor,
                $playlist->version_patch,
            ]);
            $playlist->update([
                'version_major' => $newVersion[0],
                'version_minor' => $newVersion[1],
                'version_patch' => $newVersion[2],
            ]);
        }

        $playlist->load('songs');

        return response()->json([
            'data' => new PlaylistResource($playlist),
            'songs' => SongResource::collection($playlist->songs),
        ]);
    }
}
