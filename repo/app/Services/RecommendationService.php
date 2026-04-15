<?php

namespace App\Services;

use App\Models\BehaviorEvent;
use App\Models\Song;
use App\Models\UserProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecommendationService
{
    /**
     * Minimum events before personalized recommendations kick in.
     */
    const COLD_START_THRESHOLD = 5;

    /**
     * Maximum number of recommendations to return.
     */
    const RECOMMENDATION_LIMIT = 20;

    /**
     * Generate recommendations for a user.
     *
     * @return \Illuminate\Support\Collection List of recommended songs with scores
     */
    public function recommend(int $userId): Collection
    {
        $eventCount = BehaviorEvent::where('user_id', $userId)->count();

        if ($eventCount < self::COLD_START_THRESHOLD) {
            return $this->coldStartRecommendations($userId);
        }

        return $this->personalizedRecommendations($userId);
    }

    /**
     * Cold-start recommendations for users with few events.
     * Combines popular songs and content-similar songs.
     */
    private function coldStartRecommendations(int $userId): Collection
    {
        // Get IDs of songs the user has already interacted with
        $interactedSongIds = BehaviorEvent::where('user_id', $userId)
            ->where('target_type', 'song')
            ->distinct()
            ->pluck('target_id')
            ->toArray();

        // 1. Popular songs in last 7 days
        $sevenDaysAgo = Carbon::now()->subDays(7);

        $popularSongIds = BehaviorEvent::where('target_type', 'song')
            ->where('server_timestamp', '>=', $sevenDaysAgo)
            ->selectRaw('target_id, COUNT(*) as event_count')
            ->groupBy('target_id')
            ->orderByDesc('event_count')
            ->limit(10)
            ->pluck('target_id')
            ->toArray();

        // 2. Content-similar songs based on user's interacted songs
        $contentSimilarIds = [];

        if (!empty($interactedSongIds)) {
            // Get tags and artists from songs the user has interacted with
            $userSongs = Song::with('tags')
                ->whereIn('id', $interactedSongIds)
                ->get();

            $userTags    = $userSongs->flatMap(fn ($song) => $song->tags->pluck('tag'))->unique()->toArray();
            $userArtists = $userSongs->pluck('artist')->unique()->toArray();

            if (!empty($userTags) || !empty($userArtists)) {
                $contentQuery = Song::where('publish_state', 'published')
                    ->whereNotIn('id', $interactedSongIds);

                $contentQuery->where(function ($q) use ($userTags, $userArtists) {
                    if (!empty($userTags)) {
                        $q->whereHas('tags', function ($tq) use ($userTags) {
                            $tq->whereIn('tag', $userTags);
                        });
                    }
                    if (!empty($userArtists)) {
                        $q->orWhereIn('artist', $userArtists);
                    }
                });

                $contentSimilarIds = $contentQuery->limit(10)->pluck('id')->toArray();
            }
        }

        // 3. Merge, deduplicate, exclude already interacted
        $candidateIds = array_unique(array_merge($popularSongIds, $contentSimilarIds));
        $candidateIds = array_values(array_diff($candidateIds, $interactedSongIds));

        // Limit
        $candidateIds = array_slice($candidateIds, 0, self::RECOMMENDATION_LIMIT);

        // Load songs with tags
        $songs = Song::with('tags')
            ->whereIn('id', $candidateIds)
            ->where('publish_state', 'published')
            ->get();

        // Return with a default score of 0 for cold start
        return $songs->map(function ($song) {
            $song->recommendation_score = 0;
            return $song;
        })->values();
    }

    /**
     * Personalized recommendations based on the user's computed profile.
     */
    private function personalizedRecommendations(int $userId): Collection
    {
        // 1. Load user profile
        $profile = UserProfile::where('user_id', $userId)->first();

        // If no profile exists yet, fall back to cold start
        if (!$profile || (empty($profile->interest_tags) && empty($profile->preference_vector))) {
            return $this->coldStartRecommendations($userId);
        }

        $interestTags     = $profile->interest_tags ?? [];
        $preferenceVector = $profile->preference_vector ?? [];

        // Get IDs of songs the user has already interacted with
        $interactedSongIds = BehaviorEvent::where('user_id', $userId)
            ->where('target_type', 'song')
            ->distinct()
            ->pluck('target_id')
            ->toArray();

        // 2. Find published songs matching top interest tags or top artists
        $topTags    = array_slice(array_keys($interestTags), 0, 10);
        $topArtists = array_slice(array_keys($preferenceVector), 0, 10);

        $candidateSongs = Song::with('tags')
            ->where('publish_state', 'published')
            ->when(!empty($interactedSongIds), function ($q) use ($interactedSongIds) {
                $q->whereNotIn('id', $interactedSongIds);
            })
            ->where(function ($q) use ($topTags, $topArtists) {
                if (!empty($topTags)) {
                    $q->whereHas('tags', function ($tq) use ($topTags) {
                        $tq->whereIn('tag', $topTags);
                    });
                }
                if (!empty($topArtists)) {
                    $q->orWhereIn('artist', $topArtists);
                }
            })
            ->limit(200) // fetch a reasonable pool of candidates
            ->get();

        // 3. Score each candidate
        $scored = $candidateSongs->map(function ($song) use ($interestTags, $preferenceVector) {
            $score = 0;

            // Sum tag scores for matching tags
            foreach ($song->tags as $songTag) {
                $tag = $songTag->tag;
                if (isset($interestTags[$tag])) {
                    $score += $interestTags[$tag];
                }
            }

            // Add artist score
            if (isset($preferenceVector[$song->artist])) {
                $score += $preferenceVector[$song->artist];
            }

            $song->recommendation_score = round($score, 4);
            return $song;
        });

        // 4. Sort by score DESC, limit
        return $scored->sortByDesc('recommendation_score')
            ->take(self::RECOMMENDATION_LIMIT)
            ->values();
    }
}
