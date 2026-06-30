<?php

use Livewire\Component;
use App\Models\Post;
use App\Models\Interaction;
use App\Models\Interest;
use Illuminate\Support\Collection;

new class extends Component {
    // [ 'Category Name' => count ] — cumulative across all batches
    public array $categoryStats = [];
    public int $totalSuggested = 0;
    public int $batchCount = 0;

    public array $recommendedPostIds = []; // cumulative raw post IDs, not just categories
    public int $thresholdBatches = 5; // adjustable in UI
    public ?array $metrics = null; // null until calculated

    protected $listeners = ['posts-recommended' => 'onPostsRecommended'];

    public function onPostsRecommended(array $postIds, bool $reset = false): void
    {
        if ($reset) {
            $this->categoryStats = [];
            $this->totalSuggested = 0;
            $this->batchCount = 0;
            $this->recommendedPostIds = [];
            $this->metrics = null;
        }
        $this->recommendedPostIds = array_merge($this->recommendedPostIds, $postIds);

        if (empty($postIds)) {
            return;
        }

        // Load just the categories for these posts — no full post data needed
        $posts = Post::with('categories:id,name')
            ->whereIn('id', $postIds)
            ->get(['id']);

        foreach ($posts as $post) {
            foreach ($post->categories as $category) {
                $name = $category->name;
                $this->categoryStats[$name] = ($this->categoryStats[$name] ?? 0) + 1;
            }
        }

        $this->totalSuggested += count($postIds);
        $this->batchCount++;

        // Sort by count descending so highest stays on top
        arsort($this->categoryStats);
    }

    public function clear(): void
    {
        $this->categoryStats = [];
        $this->totalSuggested = 0;
        $this->batchCount = 0;
        $this->recommendedPostIds = [];
        $this->metrics = null;
    }
    public function calculateMetrics(): void
    {
        $userId = auth()->id();
        if (!$userId || empty($this->recommendedPostIds)) {
            return;
        }

        // Same vector source RecommendationService uses for similarity
        $userCategoryIds = $this->getUserInterestCategoryIds($userId);

        if (empty($userCategoryIds)) {
            $this->metrics = ['error' => 'No interest signal available yet for this user.'];
            return;
        }

        $k = count($this->recommendedPostIds);

        // Relevant Found in Top K: recommended posts matching user's interest categories
        $relevantFound = Post::whereIn('id', $this->recommendedPostIds)->whereHas('categories', fn($q) => $q->whereIn('categories.id', $userCategoryIds))->count();

        // Total Relevant in Dataset: ALL unseen posts DB-wide matching those categories
        $seenPostIds = Interaction::where('user_id', $userId)->pluck('post_id');

        $totalRelevant = Post::whereNotIn('id', $seenPostIds)->whereHas('categories', fn($q) => $q->whereIn('categories.id', $userCategoryIds))->count();

        $precision = $k > 0 ? $relevantFound / $k : 0;
        $recall = $totalRelevant > 0 ? $relevantFound / $totalRelevant : 0;

        $this->metrics = [
            'k' => $k,
            'relevantFound' => $relevantFound,
            'totalRelevant' => $totalRelevant,
            'precision' => round($precision, 3),
            'recall' => round($recall, 3),
        ];
    }

    private function getUserInterestCategoryIds(int $userId): array
    {
        // Behavioral signal first (same priority as RecommendationService)
        $fromInterests = Interest::where('user_id', $userId)->where('weight', '>=', 3)->pluck('category_id');

        if ($fromInterests->isNotEmpty()) {
            return $fromInterests->toArray();
        }
        return [];
    }
};
?>

<div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">

    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">
            Recommendation Stats
        </h3>
        @if ($totalSuggested > 0)
            <button class="text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                    wire:click="clear">
                Clear
            </button>
        @endif
    </div>
    {{-- Metrics panel --}}
    @if ($metrics)
        @if (isset($metrics['error']))
            <p class="mb-3 text-xs text-amber-600 dark:text-amber-400">{{ $metrics['error'] }}</p>
        @else
            <div class="mb-4 rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">
                        Precision@ {{ $metrics['k'] }} / Recall@ {{ $metrics['k'] }}
                    </span>
                    <button class="text-xs text-blue-400 hover:text-blue-600"
                            wire:click="$set('metrics', null)">
                        ×
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-lg font-bold text-blue-700 dark:text-blue-300">
                            {{ number_format($metrics['precision'] * 100, 1) }}%
                        </p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Precision</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-blue-700 dark:text-blue-300">
                            {{ number_format($metrics['recall'] * 100, 1) }}%
                        </p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Recall</p>
                    </div>
                </div>
                <p class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">
                    {{ $metrics['relevantFound'] }} relevant of {{ $metrics['k'] }} shown ·
                    {{ $metrics['totalRelevant'] }} relevant total in dataset
                </p>
            </div>
        @endif
    @elseif ($batchCount >= $thresholdBatches)
        <button class="mb-4 w-full rounded-lg bg-blue-600 py-2 text-xs font-medium text-white transition hover:bg-blue-500"
                wire:click="calculateMetrics">
            Calculate Precision & Recall
        </button>
    @endif
    {{-- Threshold control --}}
    <div class="mt-4 flex items-center gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-700">
        <label class="text-xs text-zinc-400 dark:text-zinc-500">
            Calculate after
        </label>
        <input class="w-14 rounded border border-zinc-200 bg-transparent px-1 py-0.5 text-xs dark:border-zinc-600"
               type="number"
               min="1"
               wire:model.live="thresholdBatches" />
        <span class="text-xs text-zinc-400 dark:text-zinc-500">batches</span>
    </div>
    @if ($totalSuggested === 0)
        <p class="text-xs text-zinc-400 dark:text-zinc-500">
            Waiting for feed to load...
        </p>
    @else
        {{-- Summary line --}}
        <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
            <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $totalSuggested }}</span>
            posts suggested across
            <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $batchCount }}</span>
            {{ Str::plural('batch', $batchCount) }}
        </p>

        {{-- Category breakdown --}}
        <div class="space-y-2">
            @foreach ($categoryStats as $name => $count)
                @php
                    $pct = $totalSuggested > 0 ? round(($count / $totalSuggested) * 100) : 0;
                @endphp
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <span class="truncate text-xs font-medium text-zinc-700 dark:text-zinc-300">
                            {{ $name }}
                        </span>
                        <span class="ml-2 shrink-0 text-xs text-zinc-400 dark:text-zinc-500">
                            {{ $count }} post{{ $count !== 1 ? 's' : '' }}
                        </span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <div class="h-1.5 rounded-full bg-blue-500 transition-all duration-500"
                             style="width: {{ $pct }}%">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Note: posts with multiple categories count once per category --}}
        @if (array_sum($categoryStats) > $totalSuggested)
            <p class="mt-3 text-xs text-zinc-400 dark:text-zinc-500">
                * Posts with multiple categories are counted in each.
            </p>
        @endif
    @endif
</div>
