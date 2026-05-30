<?php

use Livewire\Component;

new class extends Component {
    public $title;
    public $category_id;
    public $content;
    public $image_url;
    public $categories;

    public function mount()
    {
        $this->categories = \App\Models\Category::all();
    }
    protected $rules = [
        'title' => 'required|min:5|max:255',
        'category_id' => 'required|exists:categories,id',
        'content' => 'required|min:10',
        'image_url' => 'nullable|url',
    ];

    public function save()
    {
        $this->validate();

        Post::create([
            'user_id' => Auth::id(), // Assuming you have a user_id on Post for ownership
            'category_id' => $this->category_id,
            'title' => $this->title,
            'content' => $this->content,
            'image_url' => $this->image_url,
            'published_at' => now(),
        ]);

        $this->reset(['title', 'category_id', 'content', 'image_url']);

        // Notify the Feed component to refresh
        $this->dispatch('post-created');

        session()->flash('message', 'Post published successfully!');
    } //
};
?>

<div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
    <h2 class="mb-4 text-lg font-bold text-gray-800">Create a New Post</h2>

    @if (session()->has('message'))
        <div class="mb-4 rounded-lg bg-green-100 p-3 text-sm text-green-700">
            {{ session('message') }}
        </div>
    @endif

    <form class="space-y-4"
          wire:submit.prevent="save">
        <!-- Title -->
        <div>
            <input class="w-full rounded-lg border-gray-200 focus:border-blue-500 focus:ring-blue-500"
                   type="text"
                   wire:model="title"
                   placeholder="What's on your mind?">
            @error('title')
                <span class="text-xs text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <!-- Category Dropdown -->
            <div>
                <select class="w-full rounded-lg border-gray-200 focus:border-blue-500 focus:ring-blue-500"
                        wire:model="category_id">
                    <option value="">Select Category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
                    <span class="text-xs text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <!-- Image URL -->
            <div>
                <input class="w-full rounded-lg border-gray-200 focus:border-blue-500 focus:ring-blue-500"
                       type="text"
                       wire:model="image_url"
                       placeholder="Image URL (optional)">
                @error('image_url')
                    <span class="text-xs text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <!-- Content -->
        <div>
            <textarea class="w-full rounded-lg border-gray-200 focus:border-blue-500 focus:ring-blue-500"
                      wire:model="content"
                      rows="3"
                      placeholder="Share your thoughts..."></textarea>
            @error('content')
                <span class="text-xs text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
            <button class="transform rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white transition duration-200 ease-in-out hover:scale-105 hover:bg-blue-700"
                    type="submit">
                Post Content
            </button>
        </div>
    </form>
</div>
