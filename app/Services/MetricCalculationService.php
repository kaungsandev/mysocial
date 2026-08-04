<?php

namespace App\Services;

use App\Models\Interaction;
use App\Models\Interest;
use App\Models\Post;

class MetricCalculationService
{
    public function getUserInterestCategoryIds(int $userId, float $minWeight = 3.0): array
    {
        $fromInterests = Interest::where('user_id', '=', $userId, 'and')
            ->where('weight', '>=', $minWeight)
            ->pluck('category_id');

        return $fromInterests->isNotEmpty() ? $fromInterests->toArray() : [];
    }

    public function countRelevantPostsFound(array $recommendedPostIds, array $userCategoryIds): int
    {
        if (empty($recommendedPostIds) || empty($userCategoryIds)) {
            return 0;
        }

        return Post::whereIn('id', $recommendedPostIds, 'and', false)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $userCategoryIds))
            ->count();
    }

    // Relevant Found in Top K: recommended posts matching user's interest categories
    public function countTotalRelevantPosts(int $userId, array $userCategoryIds): int
    {
        if (empty($userCategoryIds)) {
            return 0;
        }

        $seenPostIds = Interaction::where('user_id', '=', $userId, 'and')->pluck('post_id');

        return Post::whereNotIn('id', $seenPostIds, 'and')
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $userCategoryIds))
            ->count();
    }

    public function calculatePrecision(int $relevantFound, int $k): float
    {
        return $k > 0 ? $relevantFound / $k : 0.0;
    }

    public function calculateRecall(int $relevantFound, int $totalRelevantInDatabase): float
    {
        return $totalRelevantInDatabase > 0 ? $relevantFound / $totalRelevantInDatabase : 0.0;
    }

    public function calculateF1(float $precision, float $recall): float
    {
        return ($precision + $recall) > 0
            ? 2 * ($precision * $recall) / ($precision + $recall)
            : 0.0;
    }

    /**
     * Single entry point that every caller (widget or evaluation tool) should use,
     * so precision/recall/F1 are always computed the same way everywhere.
     */
    public function evaluateBatch(int $userId, array $shownPostIds, array $userCategoryIds): array
    {
        $relevantFound = $this->countRelevantPostsFound($shownPostIds, $userCategoryIds);
        $totalRelevant = $this->countTotalRelevantPosts($userId, $userCategoryIds);
        $k = count($shownPostIds);

        $precision = $this->calculatePrecision($relevantFound, $k);
        $recall = $this->calculateRecall($relevantFound, $totalRelevant);
        $f1 = $this->calculateF1($precision, $recall);

        return compact('k', 'relevantFound', 'totalRelevant', 'precision', 'recall', 'f1');
    }
}
