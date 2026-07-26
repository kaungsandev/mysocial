<?php

namespace App\Services;

use App\Models\Interaction;
use App\Models\Interest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    /**
     * Get 10 recommended posts for a user at a given page offset.
     * Falls back to latest posts if not enough CF results.
     */
    public function recommend(int $userId, int $page = 1, int $perPage = 10): Collection
    {
        $offset = ($page - 1) * $perPage;

        // 1. Build the current user's item interest vector
        $userItemVector = $this->buildUserItemVector($userId);

        if ($userItemVector->isEmpty()) {
            // Cold start: no interactions yet, return latest posts
            return $this->fallback($userId, $offset, $perPage);
        }

        // 2. Get all other users who have interactions
        $otherUserIds = Interaction::where('user_id', '!=', $userId, 'and')
            ->distinct()
            ->pluck('user_id');

        if ($otherUserIds->isEmpty()) {
            return $this->fallback($userId, $offset, $perPage);
        }

        // 3. Compute cosine similarity with each other user
        $similarities = collect();

        foreach ($otherUserIds as $otherUserId) {
            $otherVector = $this->buildUserItemVector($otherUserId);
            $sim = $this->cosineSimilarity($userItemVector, $otherVector);

            if ($sim > 0) {
                $similarities->put($otherUserId, $sim);
            }
        }

        if ($similarities->isEmpty()) {
            return $this->fallback($userId, $offset, $perPage);
        }

        // 4. Sort by similarity descending, take top 20 similar users
        $topUserIds = $similarities
            ->sortDesc()
            ->take(20)
            ->keys();

        // 5. Get posts interacted with by similar users,
        //    weighted by similarity score, excluding already-seen posts
        $seenPostIds = Interaction::where('user_id', $userId)->pluck('post_id');

        $scoredPosts = Interaction::whereIn('user_id', $topUserIds)
            ->whereNotIn('post_id', $seenPostIds)
            ->select('post_id', 'user_id')
            ->get()
            ->groupBy('post_id')
            ->map(function ($interactions) use ($similarities) {
                // Score = sum of similarity weights of users who touched this post
                return $interactions->sum(fn ($i) => $similarities->get($i->user_id, 0));
            })
            ->sortDesc(); // final results sorted by score and the post_id will be the key

        $recommendedPostIds = $scoredPosts->keys()->slice($offset, $perPage);

        // 6. Fetch the actual posts, preserving score order
        $posts = Post::with(['users', 'categories'])
            ->whereIn('id', $recommendedPostIds)
            ->get()
            ->sortBy(fn ($post) => $recommendedPostIds->search($post->id))
            ->values();

        // 7. Pad with fallback if we don't have enough
        if ($posts->count() < $perPage) {
            $needed = $perPage - $posts->count();
            $existingIds = $posts->pluck('id')->merge($seenPostIds);

            $fallbackPosts = Post::with(['users', 'categories'])
                ->whereNotIn('id', $existingIds)
                ->latest('published_at')
                ->take($needed)
                ->get();

            $posts = $posts->concat($fallbackPosts);
        }

        return $posts;
    }

    /**
     * Build a weighted item vector for a user based on their interactions.
     * Vector shape: [ post_id => total_weight ]
     */
    private function buildUserItemVector(int $userId): Collection
    {
        return Interaction::where('user_id', '=', $userId, 'and')
            ->select('post_id', DB::raw('SUM(weight) as total_weight'))
            ->groupBy('post_id')
            ->pluck('total_weight', 'post_id')
            ->map(fn ($w) => (float) $w);
    }

    // Will use later in CBF
    private function buildVectorFromInterests(int $userId): Collection
    {
        return Interest::where('user_id', $userId)
            ->pluck('weight', 'category_id')
            ->map(fn ($w) => (float) $w);
    }

    /**
     * Cosine similarity between two sparse vectors (associative collections).
     * cosine similarity = A⋅B​ / ∣∣A∣∣×∣∣B∣∣
     * A⋅B = (A1​×B1​)+(A2​×B2​)+... Multiply matching ratings and sum them
     * ∣∣A∣∣= √(A1² + A2² + ...) Length of vector A
     * ∣∣B∣∣= √(B1² + B2² + ...) Length of vector B
     */
    private function cosineSimilarity(Collection $userVector, Collection $otherUserVector): float
    {
        // Dot product over shared keys
        $dot = 0.0;
        foreach ($userVector as $key => $val) {
            if ($otherUserVector->has($key)) {
                $dot += $val * $otherUserVector->get($key);
            }
        }

        if ($dot === 0.0) {
            return 0.0;
        }

        $magA = sqrt($userVector->sum(fn ($value) => $value * $value));
        $magB = sqrt($otherUserVector->sum(fn ($value) => $value * $value));

        if ($magA === 0.0 || $magB === 0.0) {
            return 0.0;
        }

        return $dot / ($magA * $magB);
    }

    /**
     * Cold-start / pad fallback: just return latest unseen posts.
     */
    private function fallback(int $userId, int $offset, int $perPage): Collection
    {
        $seenPostIds = Interaction::where('user_id', $userId)->pluck('post_id');

        return Post::with(['users', 'categories'])
            ->whereNotIn('id', $seenPostIds)
            ->latest('published_at')
            ->skip($offset)
            ->take($perPage)
            ->get();
    }
}
