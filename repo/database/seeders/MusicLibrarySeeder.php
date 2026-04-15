<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Seeder;

class MusicLibrarySeeder extends Seeder
{
    public function run(): void
    {
        // Get or create a test user
        $user = User::firstOrCreate(
            ['username' => 'music_user'],
            [
                'password_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'display_name' => 'Music Library User',
                'is_active' => true,
            ]
        );

        // Create test songs with various states and tags
        $songs = [
            [
                'title' => 'Midnight Dreams',
                'artist' => 'Luna Echo',
                'duration_seconds' => 240,
                'audio_quality' => 'FLAC_24_96',
                'publish_state' => 'published',
                'tags' => ['ambient', 'electronic', 'chill'],
            ],
            [
                'title' => 'Electric Sunrise',
                'artist' => 'Neon Pulse',
                'duration_seconds' => 180,
                'audio_quality' => 'MP3_320',
                'publish_state' => 'published',
                'tags' => ['synth', 'pop', 'upbeat'],
            ],
            [
                'title' => 'Acoustic Moments',
                'artist' => 'Folk Stories',
                'duration_seconds' => 300,
                'audio_quality' => 'FLAC_16_44',
                'publish_state' => 'draft',
                'tags' => ['acoustic', 'folk', 'indie'],
            ],
            [
                'title' => 'Jazz Improvisation',
                'artist' => 'Blue Notes Collective',
                'duration_seconds' => 420,
                'audio_quality' => 'FLAC_24_96',
                'publish_state' => 'unpublished',
                'tags' => ['jazz', 'improvisation', 'live'],
            ],
            [
                'title' => 'Orchestral Epic',
                'artist' => 'Symphony Masters',
                'duration_seconds' => 600,
                'audio_quality' => 'FLAC_24_96',
                'publish_state' => 'published',
                'tags' => ['orchestral', 'classical', 'epic'],
            ],
            [
                'title' => 'Urban Beats',
                'artist' => 'City Rhythms',
                'duration_seconds' => 210,
                'audio_quality' => 'MP3_320',
                'publish_state' => 'published',
                'tags' => ['hip-hop', 'urban', 'rhythm'],
            ],
            [
                'title' => 'Desert Winds',
                'artist' => 'World Fusion',
                'duration_seconds' => 360,
                'audio_quality' => 'FLAC_16_44',
                'publish_state' => 'draft',
                'tags' => ['world', 'fusion', 'instrumental'],
            ],
            [
                'title' => 'Ethereal Whispers',
                'artist' => 'Luna Echo',
                'duration_seconds' => 290,
                'audio_quality' => 'FLAC_24_96',
                'publish_state' => 'published',
                'tags' => ['ambient', 'ethereal', 'vocal'],
            ],
        ];

        $createdSongs = [];
        foreach ($songs as $songData) {
            $tags = $songData['tags'];
            unset($songData['tags']);
            $songData['created_by'] = $user->id;

            $song = Song::create($songData);

            // Add tags
            foreach ($tags as $tag) {
                $song->tags()->create(['tag' => $tag]);
            }

            $createdSongs[] = $song;
        }

        // Create test albums
        $albums = [
            [
                'title' => 'Midnight Collection',
                'artist' => 'Luna Echo',
                'publish_state' => 'published',
                'created_by' => $user->id,
                'songs' => [0, 7], // Midnight Dreams, Ethereal Whispers
            ],
            [
                'title' => 'Neon Nights',
                'artist' => 'Neon Pulse',
                'publish_state' => 'published',
                'created_by' => $user->id,
                'songs' => [1, 5], // Electric Sunrise, Urban Beats
            ],
            [
                'title' => 'Indie Vibes',
                'artist' => 'Various Artists',
                'publish_state' => 'draft',
                'created_by' => $user->id,
                'songs' => [2], // Acoustic Moments
            ],
        ];

        foreach ($albums as $albumData) {
            $songIndices = $albumData['songs'];
            unset($albumData['songs']);

            $album = Album::create($albumData);

            // Attach songs to album
            foreach ($songIndices as $position => $songIndex) {
                $album->songs()->attach($createdSongs[$songIndex]->id, ['position' => $position + 1]);
            }
        }

        // Create test playlists
        $playlists = [
            [
                'title' => 'Relaxation Vibes',
                'description' => 'Perfect for unwinding and meditation',
                'publish_state' => 'published',
                'created_by' => $user->id,
                'songs' => [0, 3, 7], // Midnight Dreams, Jazz Improvisation, Ethereal Whispers
            ],
            [
                'title' => 'Dance Party',
                'description' => 'High energy tracks to get you moving',
                'publish_state' => 'published',
                'created_by' => $user->id,
                'songs' => [1, 5], // Electric Sunrise, Urban Beats
            ],
            [
                'title' => 'Work Focus',
                'description' => 'Music to maintain concentration during work',
                'publish_state' => 'draft',
                'created_by' => $user->id,
                'songs' => [4], // Orchestral Epic
            ],
        ];

        foreach ($playlists as $playlistData) {
            $songIndices = $playlistData['songs'];
            unset($playlistData['songs']);

            $playlist = Playlist::create($playlistData);

            // Attach songs to playlist
            foreach ($songIndices as $position => $songIndex) {
                $playlist->songs()->attach($createdSongs[$songIndex]->id, ['position' => $position + 1]);
            }
        }
    }
}
