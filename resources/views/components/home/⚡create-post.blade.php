<?php

use Livewire\Component;
use App\Models\Post;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use App\Services\InterActionService;
use App\Services\InterestService;

new class extends Component {
    public $title;

    public $content;
    public $image_url;
    public $categories;

    public $category_search = '';
    public $selected_categories = []; // Array of arrays: [['id' => 1, 'name' => 'Drama'], ['id' => 'new-Action', 'name' => 'Action']]

    public function mount()
    {
        $this->categories = Category::all();
    }

    protected $rules = [
        'title' => 'required|min:5|max:255',
        'content' => 'required|min:10',
        'image_url' => 'nullable|url',
        'selected_categories' => 'required|array|min:1', // Must select at least one
    ];
    // Toggle items on/off when clicked from the dropdown search suggestions
    public function toggleCategory($id, $name)
    {
        // Check if already selected
        $existingIndex = collect($this->selected_categories)->search(fn($item) => $item['name'] === $name);

        if ($existingIndex !== false) {
            // Remove if clicked again
            unset($this->selected_categories[$existingIndex]);
            $this->selected_categories = array_values($this->selected_categories);
        } else {
            // Append category structural footprint
            $this->selected_categories[] = [
                'id' => $id,
                'name' => $name,
            ];
        }
        $this->category_search = ''; // Reset search input field clear
    }
    public function removeCategory($name)
    {
        $this->selected_categories = collect($this->selected_categories)->reject(fn($item) => $item['name'] === $name)->values()->all();
    }
    public function save(InteractionService $interactionService, InterestService $interestService)
    {
        $this->validate();
        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $post = Post::create([
                'title' => $this->title,
                'content' => $this->content,
                'image_url' => $this->image_url,
                'published_at' => now(),
            ]);

            $syncIds = [];

            foreach ($this->selected_categories as $cat) {
                // If the ID starts with 'new-', create the Category entry on the fly
                if (str_starts_with($cat['id'], 'new-')) {
                    $newCat = Category::create([
                        'name' => $cat['name'],
                        'slug' => Str::slug($cat['name']),
                    ]);
                    $syncIds[] = $newCat->id;
                } else {
                    $syncIds[] = $cat['id'];
                }
            }
            // Attach via the Many-to-Many dynamic pivot table
            $post->categories()->sync($syncIds);

            $post->users()->attach(Auth::id());
            $interactionService->recordInteraction($post->id, \App\Enums\InteractionTypeEnum::POST->value);
            $interestService->updateInterest($post->id, \App\Enums\InteractionTypeEnum::POST->value, isPositiveInteraction: true);

            \Illuminate\Support\Facades\DB::commit();
            $this->reset(['title', 'selected_categories', 'content', 'image_url', 'category_search']);

            $this->dispatch('post-created', postId: $post->id);
            $this->dispatch('interest-updated');

            session()->flash('message', 'Post published!');
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\DB::rollBack();
            session()->flash('message', 'Post failed to publish.');
            throw $th;
        }
    }
    public function with()
    {
        $searchResults = [];
        $cleanSearch = trim($this->category_search);
        if (!empty($cleanSearch)) {
            // Fetch database categories matching string
            $dbCategories = Category::where('name', 'like', '%' . $cleanSearch . '%')
                ->take(5)
                ->get();

            foreach ($dbCategories as $category) {
                $searchResults[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'is_new' => false,
                ];
            }

            // If query doesn't perfectly match an existing database option, offer to spawn it
            $exactMatchExists = Category::where('name', $cleanSearch)->exists();
            if (!$exactMatchExists) {
                $searchResults[] = [
                    'id' => 'new-' . $cleanSearch,
                    'name' => $cleanSearch,
                    'is_new' => true,
                ];
            }
        }
        return [
            'searchResults' => $searchResults,
        ];
    }
};
?>

<div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
    <flux:heading class="mb-3"
                  size="md">What's on your mind?</flux:heading>

    @if (session()->has('message'))
        <div x-data="{ show: true }"
             x-show="show"
             x-init="setTimeout(() => show = false, 3000)"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <flux:callout class="mb-4"
                          variant="success">
                {{ session('message') }}
            </flux:callout>
        </div>
    @endif

    <form class="space-y-3"
          wire:submit="save">
        <flux:field>
            <flux:input wire:model="title"
                        placeholder="Title" />
            <flux:error name="title" />
        </flux:field>
        <div class="space-y-2">
            <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300">
                # Categories
            </label>

            <div class="relative">
                <flux:input type="text"
                            wire:model.live.debounce.250ms="category_search"
                            placeholder="Type a word to search or add (e.g. drama, movie)..." />

                @if (!empty($category_search))
                    <div
                         class="absolute z-10 mt-1 max-h-60 w-full overflow-hidden overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <div class="space-y-0.5 p-1.5">
                            @forelse($searchResults as $result)
                                @php
                                    // Check if item is already active in selected bucket
                                    $isSelected = collect($selected_categories)->contains('name', $result['name']);
                                @endphp
                                <button class="{{ $isSelected ? 'bg-blue-500 text-white' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700' }} flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm transition-colors"
                                        type="button"
                                        wire:click="toggleCategory('{{ $result['id'] }}', '{{ $result['name'] }}')">
                                    <span class="font-medium">
                                        #{{ strtolower($result['name']) }}
                                    </span>
                                    @if ($result['is_new'])
                                        <span
                                              class="{{ $isSelected ? 'bg-blue-600 text-white' : 'bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300' }} rounded px-2 py-0.5 text-xs">
                                            Create New Category
                                        </span>
                                    @elseif($isSelected)
                                        <span class="text-xs font-bold">Selected</span>
                                    @endif
                                </button>
                            @empty
                                <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                    No matching items found.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
            @if (count($selected_categories) > 0)
                <div
                     class="mb-2 flex flex-wrap gap-2 rounded-lg border border-dashed border-gray-200 bg-gray-50/50 p-2 dark:border-gray-700 dark:bg-gray-900/30">
                    @foreach ($selected_categories as $item)
                        <span
                              class="inline-flex items-center gap-1.5 rounded-full border border-blue-200/60 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 transition-all dark:border-blue-800/60 dark:bg-blue-900/40 dark:text-blue-300">
                            #{{ strtolower($item['name']) }}
                            <button class="font-bold hover:text-red-500 focus:outline-none"
                                    type="button"
                                    wire:click="removeCategory('{{ $item['name'] }}')">
                                &times;
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif
            @error('selected_categories')
                <span class="mt-1 text-xs text-red-500 dark:text-red-400">{{ $message }}</span>
            @enderror
        </div>

        <flux:field>
            <flux:label>Body</flux:label>
            <flux:textarea wire:model="content"
                           rows="3"
                           placeholder="What's happening?" />
            <flux:error name="content" />
        </flux:field>

        <flux:field>
            <flux:label>Image URL (optional)</flux:label>
            <flux:input type="url"
                        wire:model="image_url"
                        placeholder="https://" />
            <flux:error name="image_url" />
        </flux:field>
        <div class="flex justify-end">
            <flux:button type="submit"
                         variant="primary"
                         size="sm">Post</flux:button>
        </div>
    </form>
</div>
