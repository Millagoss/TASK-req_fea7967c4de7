<?php

namespace Tests\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Song;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongApiTest extends TestCase
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

    public function test_create_song(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/v1/songs', [
                'title'            => 'Test Song',
                'artist'           => 'Test Artist',
                'duration_seconds' => 240,
                'audio_quality'    => 'FLAC_16_44',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Test Song')
            ->assertJsonPath('publish_state', 'draft');
    }

    public function test_create_song_with_tags(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/v1/songs', [
                'title'            => 'Tagged Song',
                'artist'           => 'Artist',
                'duration_seconds' => 180,
                'audio_quality'    => 'MP3_320',
                'tags'             => ['rock', 'indie'],
            ]);

        $response->assertStatus(201);
        $this->assertCount(2, $response->json('tags'));
    }

    public function test_list_songs(): void
    {
        $user = $this->createAdminUser();
        Song::create([
            'title' => 'List Song', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/songs');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_filter_songs_by_artist(): void
    {
        $user = $this->createAdminUser();
        Song::create([
            'title' => 'S1', 'artist' => 'UniqueArtist', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);
        Song::create([
            'title' => 'S2', 'artist' => 'Other', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/songs?artist=UniqueArtist');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_filter_songs_by_publish_state(): void
    {
        $user = $this->createAdminUser();
        Song::create([
            'title' => 'Published', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'published',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);
        Song::create([
            'title' => 'Draft', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/songs?publish_state=published');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_show_song(): void
    {
        $user = $this->createAdminUser();
        $song = Song::create([
            'title' => 'Show Song', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/songs/{$song->id}");

        $response->assertStatus(200)
            ->assertJsonPath('title', 'Show Song');
    }

    public function test_update_song(): void
    {
        $user = $this->createAdminUser();
        $song = Song::create([
            'title' => 'Old Title', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/songs/{$song->id}", [
                'title' => 'New Title',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('title', 'New Title');
    }

    public function test_publish_song(): void
    {
        $user = $this->createAdminUser();
        $song = Song::create([
            'title' => 'To Publish', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/songs/{$song->id}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('publish_state', 'published');
    }

    public function test_unpublish_song(): void
    {
        $user = $this->createAdminUser();
        $song = Song::create([
            'title' => 'To Unpublish', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'published',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/songs/{$song->id}/unpublish");

        $response->assertStatus(200)
            ->assertJsonPath('publish_state', 'unpublished');
    }

    public function test_delete_draft_song(): void
    {
        $user = $this->createAdminUser();
        $song = Song::create([
            'title' => 'Delete Me', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/songs/{$song->id}");

        $response->assertStatus(204);
        $this->assertNull(Song::find($song->id));
    }

    public function test_delete_published_song_fails(): void
    {
        $user = $this->createAdminUser();
        $song = Song::create([
            'title' => 'No Delete', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'published',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/songs/{$song->id}");

        $response->assertStatus(422);
    }

    public function test_bump_version(): void
    {
        $user = $this->createAdminUser();
        $song = Song::create([
            'title' => 'Version Song', 'artist' => 'A', 'duration_seconds' => 100,
            'audio_quality' => 'MP3_320', 'publish_state' => 'draft',
            'version_major' => 1, 'version_minor' => 0, 'version_patch' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/songs/{$song->id}/version", [
                'bump' => 'minor',
            ]);

        $response->assertStatus(200);
        $song->refresh();
        $this->assertEquals(1, $song->version_minor);
    }

    public function test_unauthenticated_access(): void
    {
        $response = $this->getJson('/api/v1/songs');
        $response->assertStatus(401);
    }
}
