<?php

use Livewire\Component;
use App\Models\Post;
use Illuminate\Support\Collection;

new class extends Component {
    // [ 'Category Name' => count ] — cumulative across all batches
    public array $categoryStats = [];
    public int $totalSuggested = 0;
    public int $batchCount = 0;

    protected $listeners = ['posts-recommended' => 'onPostsRecommended'];

    public function onPostsRecommended(array $postIds, bool $reset = false): void
    {
        if ($reset) {
            $this->categoryStats = [];
            $this->totalSuggested = 0;
            $this->batchCount = 0;
        }

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
