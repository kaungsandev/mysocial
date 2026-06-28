<?php

use Livewire\Component;
use App\Models\Post;
use App\Models\Interaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Services\RecommendationService;
use App\Services\InterActionService;
use App\Services\InterestService;
use App\Enums\InteractionTypeEnum;

new class extends Component {
    public Collection $posts;
    public array $likedPostIds = [];

    public ?int $newPostId = null;
    public int $page = 1;
    public int $perPage = 10;
    public bool $hasMore = true;
    public bool $loading = false;
    public ?string $lastPostTimestamp = null;
    public int $newPostsCount = 0;

    protected $listeners = ['post-created' => 'onPostCreated', 'load-more' => 'loadMore', 'refresh-feed' => 'refreshFeed'];

    private function dispatchPostsRecommended(Collection $posts, bool $reset): void
    {
        $postIds = $posts->pluck('id')->toArray();

        $this->dispatch('posts-recommended', postIds: $postIds, reset: $reset);
    }
    public function loadLikedPosts(): void
    {
        if (Auth::check()) {
            $this->likedPostIds = Interaction::where('user_id', Auth::id())->where('interaction_type', InteractionTypeEnum::LIKE->value)->pluck('post_id')->toArray();
        }
    }
    public function mount()
    {
        $this->loadLikedPosts();
        $this->loadPosts();

        if ($this->posts->isNotEmpty()) {
            $this->lastPostTimestamp = $this->posts->first()->published_at;
        }
    }

    public function toggleLike(int $postId, InteractionService $interactionService, InterestService $interestService): void
    {
        $userId = Auth::id();

        if (in_array($postId, $this->likedPostIds)) {
            // 1. Remove from the raw interactions ledger
            $interactionService->removeInteraction($userId, $postId, InteractionTypeEnum::LIKE);
            $interestService->updateInterest($postId, InteractionTypeEnum::LIKE->value, isPositiveInteraction: false);

            // 3. Update local array state
            $this->likedPostIds = array_diff($this->likedPostIds, [$postId]);
        } else {
            // --- LIKE LOGIC ---
            // Run it through your single source of truth service
            $interactionService->recordInteraction($userId, $postId, InteractionTypeEnum::LIKE);
            $interestService->updateInterest($postId, InteractionTypeEnum::LIKE->value, isPositiveInteraction: true);
            // Update local array state
            $this->likedPostIds[] = $postId;
        }

        // Notify the stats widgets to instantly refresh with opacity drop
        $this->dispatch('interest-updated');
    }

    public function checkForNewPosts()
    {
        if (!$this->lastPostTimestamp) {
            return;
        }

        $newerPosts = Post::with(['users', 'categories'])
            ->whereRaw('UNIX_TIMESTAMP(published_at) > ?', [$this->lastPostTimestamp])
            ->orderByDesc('published_at')
            ->get();

        if ($newerPosts->isNotEmpty()) {
            $this->newPostsCount = $newerPosts->count();

            foreach ($newerPosts->reverse() as $post) {
                $existingIds = $this->posts->pluck('id');
                if (!$existingIds->contains($post->id)) {
                    $this->posts->prepend($post);
                }
            }

            $this->lastPostTimestamp = $newerPosts->first()->published_at;
        }
    }

    public function refreshFeed()
    {
        $this->page = 1;
        $this->newPostId = null;
        $this->newPostsCount = 0;

        $this->posts = Post::with(['users', 'categories'])
            ->latest('published_at')
            ->forPage($this->page, $this->perPage)
            ->get();

        if ($this->posts->isNotEmpty()) {
            $this->lastPostTimestamp = $this->posts->first()->published_at;
        }
        $this->dispatchPostsRecommended($this->posts, true); // true = reset stats first

        $total = Post::count();
        $this->hasMore = $this->page * $this->perPage < $total;
    }

    public function loadPosts()
    {
        $userId = auth()->id();
        $service = app(RecommendationService::class);

        $this->posts = $userId
            ? $service->recommend($userId, $this->page, $this->perPage)
            : Post::with(['users', 'categories'])
                ->latest('published_at')
                ->forPage($this->page, $this->perPage)
                ->get();
        $this->dispatchPostsRecommended($this->posts, false); // false = don't reset stats
        $this->hasMore = $this->posts->count() === $this->perPage;
    }

    public function loadMore()
    {
        if ($this->loading || !$this->hasMore) {
            return;
        }

        $this->loading = true;
        $this->page++;
        $userId = auth()->id();
        $service = app(RecommendationService::class);

        $olderPosts = $userId
            ? $service->recommend($userId, $this->page, $this->perPage)
            : Post::with(['users', 'categories'])
                ->latest('published_at')
                ->forPage($this->page, $this->perPage)
                ->get();

        if ($olderPosts->isEmpty()) {
            $this->hasMore = false;
        } else {
            foreach ($olderPosts as $post) {
                $this->posts->push($post);
            }
            $this->dispatchPostsRecommended($olderPosts, false);
            $this->hasMore = $olderPosts->count() === $this->perPage;
        }

        $this->loading = false;
    }

    public function onPostCreated($postId)
    {
        $newPost = Post::with(['users', 'categories'])->find($postId);

        if ($newPost) {
            $this->newPostId = $newPost->id;
            $this->newPostsCount = 0;

            $existingIds = $this->posts->pluck('id');
            if (!$existingIds->contains($newPost->id)) {
                $this->posts->prepend($newPost);
            }

            $this->lastPostTimestamp = $newPost->published_at;

            $this->dispatch('scroll-to-top');
        }
    }

    public function formatTimeForHumans($timestamp)
    {
        $now = now();
        $time = is_numeric($timestamp) ? \Carbon\Carbon::createFromTimestamp($timestamp) : \Carbon\Carbon::parse($timestamp);
        $diff = $time->diffInSeconds($now);

        if ($diff < 60) {
            return 'now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . 'm';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . 'h';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . 'd';
        } else {
            return $time->format('M j');
        }
    }

    public function scrollToNewPosts()
    {
        $this->newPostsCount = 0;

        $this->dispatch('scroll-to-top');
    }
};
?>

<div class="flex h-full flex-col"
     wire:poll.5s="checkForNewPosts">
    @if ($newPostsCount > 0)
        <button class="sticky top-0 z-10 mx-auto mb-2 mt-2 flex items-center gap-2 rounded-full bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-lg transition hover:bg-blue-500"
                wire:click="scrollToNewPosts">
            <flux:icon class="h-4 w-4"
                       name="arrow-up" />
            {{ $newPostsCount }} new {{ Str::plural('post', $newPostsCount) }}
        </button>
    @endif
    <div class="flex-1 overflow-y-auto"
         id="feed-container">
        <div class="space-y-0">
            <div class="flex flex-col items-center justify-center py-20 text-center"
                 wire:loading
                 wire:target="refreshFeed">
                <div class="mb-3 animate-pulse text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    Getting new posts...
                </div>
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-zinc-300 border-t-blue-500"></div>
            </div>

            <div wire:loading.remove
                 wire:target="refreshFeed">
                @forelse ($posts as $post)
                    <div class="@if ($post->id === $newPostId) animate-slide-in border-b-2 border-blue-500 bg-blue-50/50 dark:border-blue-400 dark:bg-blue-900/20 @else border-b border-zinc-200 dark:border-zinc-700 @endif px-4 py-4 transition-all hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                         wire:key="post-{{ $post->id }}">
                        <div class="flex gap-3">
                            <div class="flex-shrink-0">
                                @php
                                    $user = $post->users->first();
                                    $initials = $user ? $user->initials() : '?';
                                @endphp
                                <div
                                     class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-sm font-semibold text-white shadow">
                                    {{ $initials }}
                                </div>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $user?->name ?? 'Anonymous' }}
                                    </span>
                                    @if ($user)
                                        <span class="text-zinc-500 dark:text-zinc-400">·</span>
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                            @if ($post->id === $newPostId)
                                                <span class="text-blue-600 dark:text-blue-400">just now</span>
                                            @else
                                                {{ $this->formatTimeForHumans($post->published_at) }}
                                            @endif
                                        </span>
                                    @endif

                                    @if ($post->category)
                                        <span
                                              class="ml-auto shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                            {{ $post->category->name }}
                                        </span>
                                    @endif

                                    @if ($post->id === $newPostId)
                                        <span
                                              class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-600 dark:bg-blue-900/50 dark:text-blue-400">
                                            NEW
                                        </span>
                                    @endif
                                </div>

                                @if ($post->title)
                                    <h3 class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $post->title }}
                                    </h3>
                                @endif

                                <p class="mt-1 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                                    {{ $post->content }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($post->categories as $category)
                                        <span
                                              class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                            {{ $category->name }}
                                        </span>
                                    @endforeach

                                </div>
                                @if ($post->image_url)
                                    <div
                                         class="mt-3 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                                        <img class="h-auto w-full object-cover"
                                             src="{{ $post->image_url }}"
                                             alt="Post image"
                                             onerror="this.parentElement.style.display='none'" />
                                    </div>
                                @endif

                                <div class="mt-3 flex items-center gap-6 text-zinc-500 dark:text-zinc-400">
                                    <button class="flex items-center gap-1.5 text-xs transition hover:text-blue-500">
                                        <flux:icon class="h-4 w-4"
                                                   name="chat-bubble-left-right" />
                                        <span>Reply</span>
                                    </button>
                                    <button class="flex items-center gap-1.5 text-xs transition hover:text-green-500">
                                        <flux:icon class="h-4 w-4"
                                                   name="arrow-path-rounded-square" />
                                        <span>Repost</span>
                                    </button>
                                    @php
                                        $isLiked = in_array($post->id, $likedPostIds);
                                    @endphp
                                    <button class="{{ $isLiked ? 'text-red-500 font-semibold' : 'text-zinc-500 hover:text-red-500 dark:text-zinc-400' }} group flex items-center gap-1.5 text-xs font-medium transition duration-200 focus:outline-none"
                                            wire:click="toggleLike({{ $post->id }})"
                                            wire:loading.attr="disabled">

                                        {{-- Dynamic Heart Icon transformation --}}
                                        <flux:icon class="{{ $isLiked ? 'text-red-500' : 'text-zinc-400 dark:text-zinc-500 group-hover:text-red-500' }} h-4 w-4 transform transition-transform duration-200 group-active:scale-125"
                                                   name="heart"
                                                   :variant="$isLiked ? 'solid' : 'outline'" />

                                        <span>{{ $isLiked ? 'Liked' : 'Like' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <flux:icon class="mb-4 h-12 w-12 text-zinc-300 dark:text-zinc-600"
                                   name="chat-bubble-left-right" />
                        <p class="text-lg font-medium text-zinc-500 dark:text-zinc-400">No posts yet</p>
                        <p class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">Be the first to share something!</p>
                    </div>
                @endforelse

                @if ($loading)
                    <div class="flex items-center justify-center py-8"
                         wire:loading.class="hidden">
                        <div class="h-6 w-6 animate-spin rounded-full border-2 border-zinc-300 border-t-blue-500"></div>
                    </div>
                @endif

                @if (!$hasMore && $posts->count() > 0)
                    <div class="py-6 text-center text-sm text-zinc-400 dark:text-zinc-500"
                         wire:loading.class="hidden"
                         wire:target="refreshFeed">
                        You've reached the end
                    </div>
                @endif

                @if ($hasMore)
                    <div class="flex h-24 w-full shrink-0 items-center justify-center text-center"
                         id="sentinel"
                         wire:loading.class="hidden"
                         wire:target="refreshFeed">
                        <svg class="size-6 animate-spin self-center text-zinc-300 dark:text-zinc-600"
                             xmlns="http://www.w3.org/2000/svg"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke-width="1.5"
                             stroke="currentColor">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <style>
        @keyframes slideIn {
            0% {
                opacity: 0;
                transform: translateY(-20px);
                max-height: 0;
            }

            50% {
                opacity: 1;
            }

            100% {
                opacity: 1;
                transform: translateY(0);
                max-height: 500px;
            }
        }

        .animate-slide-in {
            animation: slideIn 0.4s ease-out;
        }
    </style>

    <script>
        document.addEventListener('livewire:initialized', () => {
            const sentinel = document.getElementById('sentinel');
            if (sentinel) {
                const observer = new IntersectionObserver(
                    (entries) => {
                        if (entries[0].isIntersecting) {
                            Livewire.dispatch('load-more');
                        }
                    }, {
                        root: document.getElementById('feed-container'),
                        rootMargin: '200px',
                        threshold: 0,
                    }
                );

                observer.observe(sentinel);
            }

            Livewire.on('scroll-to-top', () => {
                const feed = document.getElementById('feed-container');
                if (feed) {
                    feed.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });

            Livewire.on('refresh-feed', () => {
                const feed = document.getElementById('feed-container');
                if (feed) {
                    feed.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>

</div>
