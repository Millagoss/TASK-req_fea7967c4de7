<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSongRequest;
use App\Http\Requests\UpdateSongRequest;
use App\Http\Requests\UploadCoverArtRequest;
use App\Http\Resources\SongResource;
use App\Models\Song;
use App\Services\CoverArtService;
use App\Services\VersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SongController extends Controller
{
    /**
     * GET /api/v1/songs
     * Search, filter, and paginate songs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Song::query()->with('tags');

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

        // Filter by tags (comma-separated)
        if ($request->filled('tags')) {
            $tags = array_map('trim', explode(',', $request->input('tags')));
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('tag', $tags);
            });
        }

        // Filter by audio quality
        if ($request->filled('audio_quality')) {
            $query->where('audio_quality', $request->input('audio_quality'));
        }

        // Filter by publish state
        if ($request->filled('publish_state')) {
            $query->where('publish_state', $request->input('publish_state'));
        }

        // Filter by duration range
        if ($request->filled('duration_min')) {
            $query->where('duration_seconds', '>=', (int) $request->input('duration_min'));
        }
        if ($request->filled('duration_max')) {
            $query->where('duration_seconds', '<=', (int) $request->input('duration_max'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'updated_at');
        $sortDir = $request->input('sort_dir', 'desc');

        // Validate sort column to prevent injection
        if (!in_array($sortBy, ['title', 'artist', 'duration_seconds', 'updated_at'])) {
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

        $songs = $query->paginate($perPage);

        return response()->json([
            'data' => SongResource::collection($songs->items()),
            'meta' => [
                'current_page' => $songs->currentPage(),
                'last_page'    => $songs->lastPage(),
                'per_page'     => $songs->perPage(),
                'total'        => $songs->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/songs
     * Create a new song.
     */
    public function store(StoreSongRequest $request): JsonResponse
    {
        $song = Song::create([
            'title'           => $request->input('title'),
            'artist'          => $request->input('artist'),
            'duration_seconds' => $request->input('duration_seconds'),
            'audio_quality'   => $request->input('audio_quality'),
            'publish_state'   => 'draft',
            'version_major'   => 1,
            'version_minor'   => 0,
            'version_patch'   => 0,
            'created_by'      => $request->user()->id,
        ]);

        // Sync tags
        if ($request->filled('tags')) {
            $this->syncTags($song, $request->input('tags'));
        }

        $song->load('tags');

        return response()->json(new SongResource($song), 201);
    }

    /**
     * GET /api/v1/songs/{id}
     * Show a single song with its tags.
     */
    public function show(int $id): JsonResponse
    {
        $song = Song::with('tags')->findOrFail($id);

        return response()->json(new SongResource($song));
    }

    /**
     * PUT /api/v1/songs/{id}
     * Update song metadata and bump version if published.
     */
    public function update(UpdateSongRequest $request, int $id): JsonResponse
    {
        $song = Song::with('tags')->findOrFail($id);

        if ($song->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only modify your own songs.'], 403);
        }

        $data = array_filter([
            'title'           => $request->input('title'),
            'artist'          => $request->input('artist'),
            'duration_seconds' => $request->input('duration_seconds'),
            'audio_quality'   => $request->input('audio_quality'),
        ], fn ($v) => $v !== null);

        // If the song is published and we're changing metadata, bump patch version
        if ($song->publish_state === 'published' && !empty($data)) {
            $newVersion = VersioningService::incrementPatch([
                $song->version_major,
                $song->version_minor,
                $song->version_patch,
            ]);
            $data['version_major'] = $newVersion[0];
            $data['version_minor'] = $newVersion[1];
            $data['version_patch'] = $newVersion[2];
        }

        $song->update($data);

        // Sync tags
        if ($request->filled('tags')) {
            $this->syncTags($song, $request->input('tags'));
        } elseif ($request->has('tags')) {
            // If tags array is empty, clear all tags
            $song->tags()->delete();
        }

        $song->load('tags');

        return response()->json(new SongResource($song));
    }

    /**
     * DELETE /api/v1/songs/{id}
     * Delete a song (only if draft).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $song = Song::findOrFail($id);

        if ($song->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only delete your own songs.'], 403);
        }

        if ($song->publish_state !== 'draft') {
            throw ValidationException::withMessages([
                'id' => 'Only draft songs can be deleted.',
            ]);
        }

        // Delete cover art if present
        if ($song->cover_art_path) {
            CoverArtService::delete($song->cover_art_path);
        }

        $song->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/songs/{id}/publish
     * Publish a song (draft/unpublished → published).
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $song = Song::with('tags')->findOrFail($id);

        if ($song->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only publish your own songs.'], 403);
        }

        if (!in_array($song->publish_state, ['draft', 'unpublished'])) {
            throw ValidationException::withMessages([
                'id' => 'Song cannot be published from its current state.',
            ]);
        }

        $song->update(['publish_state' => 'published']);

        return response()->json(new SongResource($song));
    }

    /**
     * POST /api/v1/songs/{id}/unpublish
     * Unpublish a song (published/unpublished → unpublished).
     */
    public function unpublish(Request $request, int $id): JsonResponse
    {
        $song = Song::with('tags')->findOrFail($id);

        if ($song->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only unpublish your own songs.'], 403);
        }

        if ($song->publish_state === 'draft') {
            throw ValidationException::withMessages([
                'id' => 'Draft songs cannot be unpublished.',
            ]);
        }

        $song->update(['publish_state' => 'unpublished']);

        return response()->json(new SongResource($song));
    }

    /**
     * POST /api/v1/songs/{id}/version
     * Manually bump the version.
     */
    public function bumpVersion(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'bump' => ['required', 'in:major,minor,patch'],
        ]);

        $song = Song::with('tags')->findOrFail($id);

        if ($song->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only bump version of your own songs.'], 403);
        }

        $newVersion = VersioningService::bumpVersion(
            $request->input('bump'),
            [
                $song->version_major,
                $song->version_minor,
                $song->version_patch,
            ]
        );

        $song->update([
            'version_major' => $newVersion[0],
            'version_minor' => $newVersion[1],
            'version_patch' => $newVersion[2],
        ]);

        return response()->json(new SongResource($song));
    }

    /**
     * POST /api/v1/songs/{id}/cover-art
     * Upload cover art for a song.
     */
    public function uploadCoverArt(UploadCoverArtRequest $request, int $id): JsonResponse
    {
        $song = Song::with('tags')->findOrFail($id);

        if ($song->created_by !== $request->user()->id && !$request->user()->hasPermission('music.manage_all')) {
            return response()->json(['code' => 403, 'msg' => 'You can only upload cover art for your own songs.'], 403);
        }

        // Delete old cover art if present
        if ($song->cover_art_path) {
            CoverArtService::delete($song->cover_art_path);
        }

        // Store new cover art
        $result = CoverArtService::store($request->file('cover_art'), 'songs', $song->id);

        $song->update([
            'cover_art_path'   => $result['path'],
            'cover_art_sha256' => $result['sha256'],
        ]);

        return response()->json(new SongResource($song));
    }

    /**
     * Sync tags for a song, replacing old ones.
     */
    private function syncTags(Song $song, array $tags): void
    {
        // Delete old tags
        $song->tags()->delete();

        // Create new tags
        foreach ($tags as $tag) {
            $song->tags()->create(['tag' => $tag]);
        }
    }
}
