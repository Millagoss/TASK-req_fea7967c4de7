<?php

namespace Tests\Api;

use App\Models\Album;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Song;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbumApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        $user = User::create([
            'username'      => 'admin_' . uniqid(),
            'password_hash' => AuthService::makeHash('Admin@Password1'),
            'display_name'  => 'Admin',
            'is_active'     => true,
        ]);
        $role = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        $permissions = Permission::all();
        if ($permissions->isEmpty()) {
            foreach (['users.list','users.create','users.update','roles.list','roles.create','roles.update','service_accounts.create','disciplinary.appeal','disciplinary.clear','results.review','subjects.view_pii','music.read','music.create','music.update','music.delete','music.publish','music.manage_all'] as $p) {
                Permission::firstOrCreate(['name' => $p], ['description' => $p]);
            }
            $permissions = Permission::all();
        }
        $role->permissions()->sync($permissions->pluck('id'));
        $user->roles()->sync([$role->id]);
        return $user;
    }

    private function actingAsAdmin()
    {
        $user = $this->createAdminUser();
        return $this->actingAs($user, 'sanctum');
    }

    public function test_crud_album(): void
    {
        $user = $this->createAdminUser();

        // Create
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/albums', [
                'title'  => 'Test Album',
                'artist' => 'Test Artist',
            ]);
        $response->assertStatus(201)
            ->assertJsonPath('title', 'Test Album')
            ->assertJsonPath('publish_state', 'draft');
        $albumId = $response->json('id');

        // Read
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/albums/{$albumId}")
            ->assertStatus(200)
            ->assertJsonPath('title', 'Test Album');

        // Update
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/albums/{$albumId}", [
                'title' => 'Updated Album',
            ])
            ->assertStatus(200)
            ->assertJsonPath('title', 'Updated Album');
    }

    public function test_publish_unpublish_album(): void
    {
        $user = $this->createAdminUser();
        $album = Album::create([
            'title' => 'PubAlbum', 'artist' => 'A', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        // Publish
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/albums/{$album->id}/publish")
            ->assertStatus(200)
            ->assertJsonPath('publish_state', 'published');

        // Unpublish
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/albums/{$album->id}/unpublish")
            ->assertStatus(200)
            ->assertJsonPath('publish_state', 'unpublished');
    }

    public function test_add_song_to_album(): void
    {
        $user = $this->createAdminUser();
        $album = Album::create([
            'title' => 'SongAlbum', 'artist' => 'A', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);
        $song = Song::create([
            'title' => 'S1', 'artist' => 'A', 'duration_seconds' => 200,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/albums/{$album->id}/songs", [
                'song_id'  => $song->id,
                'position' => 1,
            ]);

        $response->assertStatus(201);
    }

    public function test_remove_song_from_album(): void
    {
        $user = $this->createAdminUser();
        $album = Album::create([
            'title' => 'RemoveAlbum', 'artist' => 'A', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);
        $song = Song::create([
            'title' => 'S2', 'artist' => 'A', 'duration_seconds' => 200,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);
        $album->songs()->attach($song->id, ['position' => 1]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/albums/{$album->id}/songs/{$song->id}");

        $response->assertStatus(200);
        $this->assertFalse($album->fresh()->songs->contains('id', $song->id));
    }

    public function test_list_album_songs(): void
    {
        $user = $this->createAdminUser();
        $album = Album::create([
            'title' => 'ListAlbum', 'artist' => 'A', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);
        $song1 = Song::create([
            'title' => 'First', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);
        $song2 = Song::create([
            'title' => 'Second', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);
        $album->songs()->attach($song2->id, ['position' => 2]);
        $album->songs()->attach($song1->id, ['position' => 1]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/albums/{$album->id}/songs");

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('First', $data[0]['title']);
    }
}
