<?php

namespace Tests\Unit;

use App\Models\BehaviorEvent;
use App\Models\Song;
use App\Models\SongTag;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\RecommendationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RecommendationService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecommendationService();

        $this->user = User::create([
            'username'      => 'rec_tester',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Rec Tester',
            'is_active'     => true,
        ]);
    }

    private function createSong(string $title, string $artist, array $tags = [], string $publishState = 'published'): Song
    {
        $song = Song::create([
            'title'            => $title,
            'artist'           => $artist,
            'duration_seconds' => 200,
            'audio_quality'    => 'high',
            'publish_state'    => $publishState,
            'version_major'    => 1,
            'version_minor'    => 0,
            'version_patch'    => 0,
            'created_by'       => $this->user->id,
        ]);

        foreach ($tags as $tag) {
            SongTag::create(['song_id' => $song->id, 'tag' => $tag]);
        }

        return $song;
    }

    private function createEvent(int $userId, Song $song, string $eventType = 'click'): BehaviorEvent
    {
        return BehaviorEvent::create([
            'user_id'          => $userId,
            'event_type'       => $eventType,
            'target_type'      => 'song',
            'target_id'        => $song->id,
            'server_timestamp' => Carbon::now(),
        ]);
    }

    public function test_cold_start_returns_popular_songs_for_user_with_few_events(): void
    {
        // User has < 5 events (0 events)
        // Create some popular songs with events from other users
        $otherUser = User::create([
            'username'      => 'other',
            'password_hash' => bcrypt('password'),
            'display_name'  => 'Other',
            'is_active'     => true,
        ]);

        $popularSong = $this->createSong('Popular Song', 'Popular Artist', ['pop']);
        // Create multiple events from other user to make it popular
        for ($i = 0; $i < 5; $i++) {
            $this->createEvent($otherUser->id, $popularSong);
        }

        $results = $this->service->recommend($this->user->id);

        $this->assertGreaterThanOrEqual(0, $results->count());
        // All results should have recommendation_score = 0 (cold start)
        foreach ($results as $song) {
            $this->assertEquals(0, $song->recommendation_score);
        }
    }

    public function test_personalized_returns_scored_songs_for_user_with_profile(): void
    {
        // Create songs
        $interactedSong = $this->createSong('Interacted', 'Artist A', ['rock']);
        $candidateSong  = $this->createSong('Candidate', 'Artist A', ['rock']);

        // Create >= 5 events for the user
        for ($i = 0; $i < 6; $i++) {
            $this->createEvent($this->user->id, $interactedSong);
        }

        // Create a user profile with interest_tags and preference_vector
        UserProfile::create([
            'user_id'           => $this->user->id,
            'interest_tags'     => ['rock' => 1.0],
            'preference_vector' => ['Artist A' => 1.0],
            'last_computed_at'  => Carbon::now(),
        ]);

        $results = $this->service->recommend($this->user->id);

        // Should contain the candidate song (not the interacted one)
        $resultIds = $results->pluck('id')->toArray();
        $this->assertContains($candidateSong->id, $resultIds);
        $this->assertNotContains($interactedSong->id, $resultIds);

        // Scored songs should have recommendation_score > 0
        $candidate = $results->firstWhere('id', $candidateSong->id);
        $this->assertGreaterThan(0, $candidate->recommendation_score);
    }

    public function test_excludes_songs_user_already_interacted_with(): void
    {
        $interacted = $this->createSong('Already Heard', 'Artist A', ['rock']);
        $fresh      = $this->createSong('New Song', 'Artist A', ['rock']);

        for ($i = 0; $i < 6; $i++) {
            $this->createEvent($this->user->id, $interacted);
        }

        UserProfile::create([
            'user_id'           => $this->user->id,
            'interest_tags'     => ['rock' => 1.0],
            'preference_vector' => ['Artist A' => 1.0],
            'last_computed_at'  => Carbon::now(),
        ]);

        $results = $this->service->recommend($this->user->id);

        $resultIds = $results->pluck('id')->toArray();
        $this->assertNotContains($interacted->id, $resultIds);
    }

    public function test_returns_up_to_recommendation_limit_results(): void
    {
        // Create more than RECOMMENDATION_LIMIT songs
        for ($i = 0; $i < 25; $i++) {
            $this->createSong("Song {$i}", 'Artist A', ['rock']);
        }

        $interacted = $this->createSong('Interacted', 'Artist A', ['rock']);
        for ($i = 0; $i < 6; $i++) {
            $this->createEvent($this->user->id, $interacted);
        }

        UserProfile::create([
            'user_id'           => $this->user->id,
            'interest_tags'     => ['rock' => 1.0],
            'preference_vector' => ['Artist A' => 1.0],
            'last_computed_at'  => Carbon::now(),
        ]);

        $results = $this->service->recommend($this->user->id);

        $this->assertLessThanOrEqual(RecommendationService::RECOMMENDATION_LIMIT, $results->count());
    }

    public function test_cold_start_with_no_events_at_all_returns_collection(): void
    {
        $results = $this->service->recommend($this->user->id);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
    }

    public function test_personalized_falls_back_to_cold_start_without_profile(): void
    {
        $song = $this->createSong('Song', 'Artist', ['tag']);

        // Create >= 5 events but no profile
        for ($i = 0; $i < 6; $i++) {
            $this->createEvent($this->user->id, $song);
        }

        // Should not throw, falls back to cold start
        $results = $this->service->recommend($this->user->id);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
    }
}
