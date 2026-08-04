<?php

use Livewire\Component;
use App\Models\Post;
use App\Models\Interaction;
use App\Models\Interest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Services\CollaborativeRecommendationService;
use App\Services\ContentBasedRecommendationService;
use App\Services\InterActionService;
use App\Services\InterestService;
use App\Enums\InteractionTypeEnum;
use Livewire\Attributes\On;

new class extends Component {
    public Collection $posts;
    public array $likedPostIds = [];
    public array $sharedPostIds = [];

    public ?int $currentSelectedPostId = null;
    public bool $showReplyModal = false;
    public string $comment = '';

    public ?Post $selectedPost = null;
    public Collection $selectedPostInteractions;
    public bool $showPostModal = false;

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

    private function resolveRecommender(): CollaborativeRecommendationService|ContentBasedRecommendationService
    {
        return session('recommendation_algorithm') === 'content' ? app(ContentBasedRecommendationService::class) : app(CollaborativeRecommendationService::class);
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
            $interactionService->removeInteraction($postId, InteractionTypeEnum::LIKE->value);
            $interestService->updateInterest($postId, InteractionTypeEnum::LIKE->value, isPositiveInteraction: false);

            // 3. Update local array state
            $this->likedPostIds = array_diff($this->likedPostIds, [$postId]);
        } else {
            // --- LIKE LOGIC ---
            $interactionService->recordInteraction(userId: $userId, postId: $postId, interactionType: InteractionTypeEnum::LIKE->value);
            $interestService->updateInterest($postId, InteractionTypeEnum::LIKE->value, isPositiveInteraction: true);
            // Update local array state
            $this->likedPostIds[] = $postId;
        }
        $this->dispatch('interaction-updated-for-selectedPost', postId: $postId);
        // Notify the stats widgets to instantly refresh with opacity drop
        $this->dispatch('interest-updated');
    }

    public function sharePost(int $postId, InteractionService $interactionService, InterestService $interestService): void
    {
        $userId = Auth::id();

        if (in_array($postId, $this->sharedPostIds)) {
            // 1. Remove from the raw interactions ledger
            $interactionService->removeInteraction($postId, InteractionTypeEnum::SHARE->value);
            $interestService->updateInterest($postId, InteractionTypeEnum::SHARE->value, isPositiveInteraction: false);

            // 3. Update local array state
            $this->sharedPostIds = array_diff($this->sharedPostIds, [$postId]);
        } else {
            // --- LIKE LOGIC ---
            $interactionService->recordInteraction(userId: $userId, postId: $postId, interactionType: InteractionTypeEnum::SHARE->value);
            $interestService->updateInterest($postId, InteractionTypeEnum::SHARE->value, isPositiveInteraction: true);
            // Update local array state
            $this->sharedPostIds[] = $postId;
        }

        $this->dispatch('interaction-updated-for-selectedPost', postId: $postId);
    }
    public function checkForNewPosts()
    {
        if (!$this->lastPostTimestamp) {
            return;
        }

        $newerPosts = Post::with(['users', 'categories'])
            ->whereRaw('UNIX_TIMESTAMP(published_at) > ?', [$this->lastPostTimestamp])
            ->orderByDesc('published_at')
            ->limit(10)
            ->get();

        if ($newerPosts->isNotEmpty()) {
            $this->newPostsCount = $newerPosts->count();

            foreach ($newerPosts->reverse() as $post) {
                $existingIds = $this->posts->pluck('id');
                if (!$existingIds->contains($post->id)) {
                    $this->posts->prepend($post);
                }
            }
            $this->dispatchPostsRecommended($newerPosts, false);
            $this->lastPostTimestamp = $newerPosts->first()->published_at;
        }
    }

    public function refreshFeed()
    {
        $this->page = 1;
        $this->newPostId = null;
        $this->newPostsCount = 0;

        $service = $this->resolveRecommender();
        $userId = auth()->id();
        $this->posts = $userId
            ? $service->recommend($userId, $this->page, $this->perPage)
            : Post::with(['users', 'categories'])
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
        $service = $this->resolveRecommender();

        $this->posts = $userId
            ? $service->recommend($userId, $this->page, $this->perPage)
            : Post::with(['users', 'categories'])
                ->latest('published_at')
                ->forPage($this->page, $this->perPage)
                ->get();
        $this->dispatchPostsRecommended($this->posts, false); // false = don't reset stats

        $this->hasMore = $this->posts->count() === $this->perPage;
    }

    public function loadMore(InteractionService $interactionService, InterestService $interestService)
    {
        if ($this->loading || !$this->hasMore) {
            return;
        }
        // Before Actually loading more posts, set the posts that are already in the feed as Interaction VIEW
        foreach ($this->posts as $post) {
            $interactionService->recordInteraction(userId: auth()->id(), postId: $post->id, interactionType: InteractionTypeEnum::VIEW->value);
            $interestService->updateInterest($post->id, InteractionTypeEnum::VIEW->value, isPositiveInteraction: true);
        }

        $this->loading = true;
        $this->page++;
        $userId = auth()->id();
        $service = $this->resolveRecommender();

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
    public function openReplyModal(int $postId): void
    {
        $this->showReplyModal = true;
        $this->currentSelectedPostId = $postId;
    }
    public function closeReplyModal(): void
    {
        $this->showReplyModal = false;
        $this->reset('comment');
        $this->reset('currentSelectedPostId');
    }
    public function createComment(?int $postId = null): void
    {
        $this->validate([
            'comment' => ['required', 'string', 'max:1000'],
        ]);
        \App\Models\Comment::create([
            'user_id' => auth()->id(),
            'post_id' => $postId ?? $this->currentSelectedPostId,
            'content' => $this->comment,
        ]);
        $interactionService = app(InterActionService::class);
        $interactionService->recordInteraction(userId: auth()->id(), postId: $postId ?? $this->currentSelectedPostId, interactionType: InteractionTypeEnum::COMMENT->value);
        $this->dispatch('interaction-updated-for-selectedPost', postId: $postId ?? $this->currentSelectedPostId);
        $this->reset('comment');
        $this->reset('currentSelectedPostId');
        $this->showReplyModal = false;
    }
    public function viewPost(int $postId): void
    {
        $this->selectedPost = Post::with(['users', 'categories', 'comments.user'])->findOrFail($postId);
        $this->selectedPostInteractions = Interaction::with(['user'])
            ->where('post_id', $postId)
            ->get();
        $this->showPostModal = true;
    }
    #[On('interaction-updated-for-selectedPost')]
    public function updateSelectedPostInteractions(int $postId): void
    {
        if ($this->selectedPost && $this->selectedPost->id === $postId) {
            $this->selectedPostInteractions = Interaction::with(['user'])
                ->where('post_id', $postId)
                ->get();
        }
    }
};
?>

<div class="flex h-full flex-col">
    {{-- comment box modal --}}
    <flux:modal wire:model="showReplyModal">
        <div class="space-y-4">
            <h2 class="text-lg font-semibold">Write a Reply</h2>

            <flux:textarea wire:model="comment"
                           placeholder="Write your reply..." />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost"
                             wire:click="closeReplyModal">
                    Cancel
                </flux:button>

                <flux:button wire:click="createComment">
                    Comment
                </flux:button>
            </div>
        </div>
    </flux:modal>
    {{-- full post detail modal --}}
    <flux:modal class="max-w-3xl"
                wire:model="showPostModal">

        @if ($selectedPost)

            <div class="flex max-h-[85vh] flex-col">

                {{-- Header --}}
                <div class="flex items-start justify-between border-b border-zinc-200 pb-4 dark:border-zinc-700">

                    <div class="min-w-0">
                        <div class="font-semibold">
                            {{ $selectedPost->users->first()?->name ?? 'Anonymous' }}
                        </div>

                        <div class="text-sm text-zinc-500">
                            {{ $this->formatTimeForHumans($selectedPost->published_at) }}
                        </div>
                    </div>

                    {{-- <flux:button variant="ghost"
                                 icon="x-mark"
                                 wire:click="$set('showPostModal', false)" /> --}}

                </div>

                {{-- Scrollable Body --}}
                <div class="mt-5 flex-1 overflow-y-auto pr-1">

                    {{-- Title --}}
                    @if ($selectedPost->title)
                        <h2 class="text-xl font-semibold">
                            {{ $selectedPost->title }}
                        </h2>
                    @endif

                    {{-- Content --}}
                    <p class="mt-3 whitespace-pre-line leading-7 text-zinc-700 dark:text-zinc-300">
                        {{ $selectedPost->content }}
                    </p>

                    {{-- Categories --}}
                    @if ($selectedPost->categories->count())
                        <div class="mt-4 flex flex-wrap gap-2">

                            @foreach ($selectedPost->categories as $category)
                                <span
                                      class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">

                                    #{{ $category->name }}

                                </span>
                            @endforeach

                        </div>
                    @endif

                    {{-- Image --}}
                    @if ($selectedPost->image_url)
                        <img class="mt-5 w-full rounded-xl border border-zinc-200 dark:border-zinc-700"
                             src="{{ $selectedPost->image_url }}">
                    @endif

                    {{-- Stats --}}
                    <div
                         class="mt-6 flex gap-6 border-y border-zinc-200 py-3 text-sm text-zinc-500 dark:border-zinc-700">

                        <span>
                            ❤️
                            {{ $selectedPostInteractions->where('interaction_type', InteractionTypeEnum::LIKE->value)->count() }}
                            Likes
                        </span>

                        <span>
                            💬
                            {{ $selectedPostInteractions->where('interaction_type', InteractionTypeEnum::COMMENT->value)->count() }}
                            Comments
                        </span>

                        <span>
                            🔄
                            {{ $selectedPostInteractions->where('interaction_type', InteractionTypeEnum::SHARE->value)->count() }}
                            Shares
                        </span>

                    </div>

                    {{-- Actions --}}
                    <div class="flex justify-around border-b border-zinc-200 py-2 dark:border-zinc-700">

                        @if ($selectedPostInteractions->where('user_id', auth()->id())->where('interaction_type', InteractionTypeEnum::LIKE->value)->count() > 0)
                            <flux:button variant="ghost"
                                         wire:click="toggleLike({{ $selectedPost->id }})">
                                <flux:icon class="h-4 w-4 text-red-500 dark:text-red-400"
                                           name="heart"
                                           variant="solid" />
                            </flux:button>
                        @else
                            <flux:button variant="ghost"
                                         icon="heart"
                                         wire:click="toggleLike({{ $selectedPost->id }})">
                                Like
                            </flux:button>
                        @endif
                        <flux:button variant="ghost"
                                     icon="chat-bubble-left-right">

                            Comment

                        </flux:button>

                        @if ($selectedPostInteractions->where('user_id', auth()->id())->where('interaction_type', InteractionTypeEnum::SHARE->value)->count() > 0)
                            <flux:button variant="ghost"
                                         wire:click="sharePost({{ $selectedPost->id }})">
                                <flux:icon class="h-4 w-4 -rotate-45"
                                           name="paper-airplane"
                                           variant="solid" />
                            </flux:button>
                        @else
                            <flux:button variant="ghost"
                                         icon="arrow-path-rounded-square"
                                         wire:click="sharePost({{ $selectedPost->id }})">
                                Share
                            </flux:button>
                        @endif
                    </div>

                    {{-- Write Comment --}}
                    <div class="mt-5">

                        <flux:textarea wire:model.defer="comment"
                                       rows="3"
                                       placeholder="Write a comment..." />

                        <div class="mt-3 flex justify-end">

                            <flux:button wire:click="createComment({{ $selectedPost->id }})">

                                Post Comment

                            </flux:button>

                        </div>

                    </div>

                    {{-- Comment List --}}
                    <div class="mt-8">

                        <h3 class="mb-4 font-semibold">
                            Comments
                        </h3>

                        <div class="space-y-4">

                            @forelse($selectedPost->comments as $comment)
                                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">

                                    <div class="flex items-center justify-between">

                                        <div>

                                            <div class="font-medium">
                                                {{ $comment->user->name }}
                                            </div>

                                            <div class="text-xs text-zinc-500">
                                                {{ $comment->created_at->diffForHumans() }}
                                            </div>

                                        </div>

                                    </div>

                                    <p class="mt-3 whitespace-pre-line text-sm">
                                        {{ $comment->content }}
                                    </p>

                                    {{-- <div class="mt-3 flex gap-5 text-xs text-zinc-500">

                                        <button class="hover:text-blue-500">
                                            Like
                                        </button>

                                        <button class="hover:text-blue-500">
                                            Reply
                                        </button>

                                    </div> --}}

                                </div>

                            @empty

                                <div class="py-10 text-center text-sm text-zinc-500">

                                    No comments yet.

                                </div>
                            @endforelse

                        </div>

                    </div>

                </div>

            </div>

        @endif

    </flux:modal>
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
                 wire:target="refresh-feed">
                <div class="mb-3 animate-pulse text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    Getting new posts...
                </div>
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-zinc-300 border-t-blue-500"></div>
            </div>

            <div wire:loading.remove
                 wire:target="refresh-feed">
                @forelse ($posts as $post)
                    <div class="@if ($post->id === $newPostId) animate-slide-in border-b-2 border-blue-500 bg-blue-50/50 dark:border-blue-400 dark:bg-blue-900/20 @else border-b border-zinc-200 dark:border-zinc-700 @endif px-4 py-4 transition-all hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                         wire:click="viewPost({{ $post->id }})"
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
                                              class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                            #{{ $category->name }}
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
                                    <button class="flex items-center gap-1.5 text-xs transition hover:text-blue-500"
                                            wire:click.stop="openReplyModal({{ $post->id }})">
                                        <flux:icon class="h-4 w-4"
                                                   name="chat-bubble-left-right" />
                                        <span>Reply</span>
                                    </button>
                                    @if (in_array($post->id, $sharedPostIds))
                                        <button class="flex items-center gap-1.5 text-xs transition hover:text-green-500"
                                                wire:click.stop="sharePost({{ $post->id }})">
                                            <flux:icon class="h-4 w-4 -rotate-45"
                                                       name="paper-airplane"
                                                       variant='solid' />
                                            <span>Shared</span>
                                        </button>
                                    @else
                                        <button class="flex items-center gap-1.5 text-xs transition hover:text-green-500"
                                                wire:click.stop="sharePost({{ $post->id }})">

                                            <flux:icon class="h-4 w-4"
                                                       name="arrow-path-rounded-square" />
                                            <span>Share</span>
                                        </button>
                                    @endif
                                    @php
                                        $isLiked = in_array($post->id, $likedPostIds);
                                    @endphp
                                    <button class="{{ $isLiked ? 'text-red-500 font-semibold' : 'text-zinc-500 hover:text-red-500 dark:text-zinc-400' }} group flex items-center gap-1.5 text-xs font-medium transition duration-200 focus:outline-none"
                                            wire:click.stop="toggleLike({{ $post->id }})"
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
                        <div class="h-6 w-6 animate-spin rounded-full border-2 border-zinc-300 border-t-blue-500">
                        </div>
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
