<?php

namespace App\Services;

use App\Models\Interaction;
use App\Models\Interest;
use App\Models\Post;

class MetricCalculationService
{
    public function getUserInterestCategoryIds(int $userId): array
    {

        $fromInterests = Interest::where('user_id', '=', $userId, 'and')->where('weight', '>=', 3)->pluck('category_id');

        if ($fromInterests->isNotEmpty()) {
            return $fromInterests->toArray();
        }

        return [];
    }

    public function countRelevantPostsFound(array $recommendedPostIds, array $userCategoryIds): int
    {
        return Post::whereIn('id', $recommendedPostIds, 'and', false)->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $userCategoryIds))->count();
    }

    // Relevant Found in Top K: recommended posts matching user's interest categories
    public function countTotalRelevantPosts(int $userId, array $userCategoryIds): int
    {
        $seenPostIds = Interaction::where('user_id', '=', $userId, 'and')->pluck('post_id');

        return Post::whereNotIn('id', $seenPostIds, 'and')->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $userCategoryIds))->count();
    }

    public function calculatePrecision(int $relevantFound, int $k): float
    {
        return $k > 0 ? $relevantFound / $k : 0;
    }

    public function calculateRecall(int $relevantFound, int $totalRelevantInDatabase): float
    {
        return $totalRelevantInDatabase > 0 ? $relevantFound / $totalRelevantInDatabase : 0;
    }
}
