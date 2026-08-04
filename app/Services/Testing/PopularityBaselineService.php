<?php

namespace App\Services\Testing;

use App\Models\Interaction;
use App\Models\Post;
use Illuminate\Support\Collection;

class PopularityBaselineService
{
    /**
     * Control condition: most-interacted-with unseen posts, no personalization at all.
     */
    public function recommend(int $userId, int $page = 1, int $perPage = 10): Collection
    {
        $offset = ($page - 1) * $perPage;
        $seenPostIds = Interaction::where('user_id', $userId)->pluck('post_id');

        $popularPostIds = Interaction::selectRaw('post_id, SUM(weight) as total_weight')
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
}
