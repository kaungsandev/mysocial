<?php

use Livewire\Component;
use App\Models\Post;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $title;
    public $category_id;
    public $content;
    public $image_url;
    public $categories;

    public function mount()
    {
        $this->categories = Category::all();
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

        $post = Post::create([
            'category_id' => $this->category_id,
            'title' => $this->title,
            'content' => $this->content,
            'image_url' => $this->image_url,
            'published_at' => now(),
        ]);

        $post->users()->attach(Auth::id());

        $this->reset(['title', 'category_id', 'content', 'image_url']);

        $this->dispatch('post-created', postId: $post->id);

        session()->flash('message', 'Post published!');
    }
};
?>

<div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
    <flux:heading size="md" class="mb-3">What's on your mind?</flux:heading>

    @if (session()->has('message'))
        <div x-data="{ show: true }"
             x-show="show"
             x-init="setTimeout(() => show = false, 3000)"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <flux:callout class="mb-4" variant="success">
                {{ session('message') }}
            </flux:callout>
        </div>
    @endif

    <form wire:submit="save" class="space-y-3">
        <flux:field>
            <flux:input wire:model="title" placeholder="Title" />
            <flux:error name="title" />
        </flux:field>

        <flux:field>
            <flux:select wire:model="category_id" placeholder="Select category">
                <flux:select.option value="">Category</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="category_id" />
        </flux:field>

        <flux:field>
            <flux:input wire:model="image_url" type="url" placeholder="Image URL (optional)" />
            <flux:error name="image_url" />
        </flux:field>

        <flux:field>
            <flux:textarea wire:model="content" rows="3" placeholder="What's happening?" />
            <flux:error name="content" />
        </flux:field>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" size="sm">Post</flux:button>
        </div>
    </form>
</div>
