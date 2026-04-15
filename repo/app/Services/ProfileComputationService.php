<?php

namespace App\Services;

use App\Models\BehaviorEvent;
use App\Models\Song;
use App\Models\UserProfile;
use Carbon\Carbon;

class ProfileComputationService
{
    /**
     * Default event type weights for scoring.
     */
    const DEFAULT_WEIGHTS = [
        'browse'   => 1,
        'search'   => 1,
        'click'    => 2,
        'favorite' => 3,
        'rate'     => 5,
        'comment'  => 2,
    ];

    /**
     * Half-life in days for exponential decay.
     */
    const HALF_LIFE_DAYS = 30;

    /**
     * How far back to look for events.
     */
    const EVENT_WINDOW_DAYS = 90;

    /**
     * Compute the behavior profile for a single user.
     */
    public function computeForUser(int $userId): UserProfile
    {
        $cutoff = Carbon::now()->subDays(self::EVENT_WINDOW_DAYS);

        $events = BehaviorEvent::where('user_id', $userId)
            ->where('server_timestamp', '>=', $cutoff)
            ->get();

        $tagScores    = [];
        $artistScores = [];

        foreach ($events as $event) {
            // Only process song targets
            if ($event->target_type !== 'song') {
                continue;
            }

            $song = Song::with('tags')->find($event->target_id);
            if (!$song) {
                continue;
            }

            // Calculate age in days
            $ageDays = Carbon::parse($event->server_timestamp)->diffInSeconds(Carbon::now()) / 86400.0;

            // Base weight for this event type (configurable via config/services.php)
            $weights = config('services.profile_weights', self::DEFAULT_WEIGHTS);
            $baseWeight = $weights[$event->event_type] ?? 1;

            // Apply exponential decay: weight * (0.5 ^ (age_days / half_life))
            $decay         = pow(0.5, $ageDays / self::HALF_LIFE_DAYS);
            $weightedScore = $baseWeight * $decay;

            // Accumulate tag scores
            foreach ($song->tags as $songTag) {
                $tag = $songTag->tag;
                $tagScores[$tag] = ($tagScores[$tag] ?? 0) + $weightedScore;
            }

            // Accumulate artist scores
            $artist = $song->artist;
            $artistScores[$artist] = ($artistScores[$artist] ?? 0) + $weightedScore;
        }

        // Normalize tag scores (max = 1.0)
        $tagScores = $this->normalize($tagScores);

        // Normalize artist scores (max = 1.0)
        $artistScores = $this->normalize($artistScores);

        // Upsert the profile
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'interest_tags'     => $tagScores,
                'preference_vector' => $artistScores,
                'last_computed_at'  => Carbon::now(),
            ]
        );

        return $profile;
    }

    /**
     * Recompute profiles for all users who have behavior events.
     *
     * @return int Number of profiles updated
     */
    public function computeAll(): int
    {
        $userIds = BehaviorEvent::distinct()->pluck('user_id');
        $count   = 0;

        foreach ($userIds as $userId) {
            $this->computeForUser($userId);
            $count++;
        }

        return $count;
    }

    /**
     * Normalize a score map so the maximum value equals 1.0.
     * Returns rounded values to 4 decimal places.
     */
    private function normalize(array $scores): array
    {
        if (empty($scores)) {
            return [];
        }

        $max = max($scores);

        if ($max <= 0) {
            return [];
        }

        $normalized = [];
        foreach ($scores as $key => $value) {
            $normalized[$key] = round($value / $max, 4);
        }

        // Sort descending by score
        arsort($normalized);

        return $normalized;
    }
}
