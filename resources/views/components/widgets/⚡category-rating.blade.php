<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public array $stats = [];

    #[On('interest-updated')]
    public function loadStats(): void
    {
        $userId = Auth::id();

        if (!$userId) {
            $this->stats = [];
            return;
        }

        // Fetch user interests joined with categories to get the names
        $interests = DB::table('interests')->join('categories', 'interests.category_id', '=', 'categories.id')->where('interests.user_id', $userId)->select('categories.name', 'interests.weight')->orderByDesc('interests.weight')->get();

        if ($interests->isEmpty()) {
            $this->stats = [];
            return;
        }

        // Find the maximum weight to act as our 100% baseline
        $maxWeight = $interests->max('weight');

        // Prevent division by zero if max weight is 0
        $maxWeight = $maxWeight > 0 ? $maxWeight : 1;

        // Map weights into a 0 - 100% range
        $this->stats = $interests
            ->map(function ($interest) use ($maxWeight) {
                return [
                    'category' => $interest->name,
                    'percentage' => round(($interest->weight / $maxWeight) * 100),
                ];
            })
            ->toArray();
    }

    public function mount(): void
    {
        $this->loadStats();
    }
};
?>

<div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">

    {{-- Header Section --}}
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">
            Category Rating
        </h3>
        <div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"
             wire:loading></div>
    </div>

    {{-- Stats Content Container --}}
    <div wire:loading.class="opacity-40 pointer-events-none transition-opacity duration-200">
        @if (count($stats) > 0)
            <div class="space-y-2">
                @foreach ($stats as $stat)
                    <div>
                        {{-- Label & Metric row --}}
                        <div class="mb-1 flex items-center justify-between">
                            <span class="truncate text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                #{{ strtolower($stat['category']) }}
                            </span>
                            <span class="ml-2 shrink-0 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ $stat['percentage'] }}%
                            </span>
                        </div>

                        {{-- Progress Bar --}}
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <div class="h-1.5 rounded-full bg-blue-500 transition-all duration-500"
                                 style="width: {{ $stat['percentage'] }}%">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Empty State (matches the clean textual feedback vibe) --}}
            <p class="text-xs text-zinc-400 dark:text-zinc-500">
                No interaction history tracked yet. Interact with posts to build your profile.
            </p>
        @endif
    </div>
</div>
