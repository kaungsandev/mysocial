<?php

namespace App\Services;

use App\Models\Interaction;
use App\Models\Interest;
use App\Models\Post;
use App\Services\Concerns\CalculatesCosineSimilarity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContentBasedRecommendationService
{
    use CalculatesCosineSimilarity;

    protected function popularityFallback(int $userId, int $offset, int $perPage): Collection
    {
        $seenPostIds = Interaction::where('user_id', '=', $userId, 'and')->pluck('post_id');

        $popularPostIds = Interaction::select('post_id', DB::raw('SUM(weight) as total_weight'))
            ->whereNotIn('post_id', $seenPostIds)
            ->groupBy('post_id')
            ->orderByDesc('total_weight')
            ->skip($offset)
            ->take($perPage)
            ->pluck('post_id');

        return Post::with(['users', 'categories'])
            ->whereIn('id', $popularPostIds)
            ->get()
            ->sortBy(fn ($post) => $popularPostIds->search($post->id))
            ->values();
    }

    /**
     * User profile vector: [category_id => weight], straight from the Interest table.
     */
    private function buildUserProfile(int $userId): Collection
    {
        return Interest::where('user_id', $userId)
            ->pluck('weight', 'category_id')
            ->map(fn ($w) => (float) $w);
    }

    /**
     * Item profile vector: [category_id => 1.0] for each
     * category the post belongs to.
     */
    private function buildItemProfile(Post $post): Collection
    {
        return $post->categories->pluck('id')->mapWithKeys(fn ($id) => [$id => 1.0]);
    }

    /**
     * Get recommended posts for a user based on content (category) similarity
     * between their interest profile and each candidate post's category profile.
     */
    public function recommend(int $userId, int $page = 1, int $perPage = 10): Collection
    {
        $offset = ($page - 1) * $perPage;

        // 1. User profile: reuse the existing Interest vector (built by InterestService)
        $userProfile = $this->buildUserProfile($userId);

        if ($userProfile->isEmpty()) {
            // Cold start: no interest signal yet — same placeholder as CF for now
            return $this->popularityFallback($userId, $offset, $perPage);
        }

        // 2. Candidate posts: everything the user hasn't seen yet
        $seenPostIds = Interaction::where('user_id', $userId)->pluck('post_id');

        $candidates = Post::with(['categories', 'users'])
            ->whereNotIn('id', $seenPostIds)
            ->get();

        // 3. Score each candidate by cosine similarity between user profile and item profile
        $scored = $candidates
            ->map(function (Post $post) use ($userProfile) {
                $itemProfile = $this->buildItemProfile($post);

                return [
                    'post' => $post,
                    'score' => $this->cosineSimilarity($userProfile, $itemProfile),
                ];
            })
            ->filter(fn ($entry) => $entry['score'] > 0)
            ->sortByDesc('score')
            ->values();

        $posts = $scored->slice($offset, $perPage)->pluck('post')->values();

        // 4. Pad with latest unseen posts if not enough scored candidates
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
}
