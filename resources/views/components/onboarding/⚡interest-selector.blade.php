<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Category;

new class extends Component {
    use WithPagination;

    public array $selected = [];

    protected $queryString = [
        'page' => ['except' => 1],
    ];

    public function toggle($id)
    {
        if (in_array($id, $this->selected)) {
            $this->selected = array_values(array_filter($this->selected, fn($i) => $i != $id));
        } else {
            $this->selected[] = $id;
        }
    }

    public function isSelected($id): bool
    {
        return in_array($id, $this->selected);
    }

    public function canContinue(): bool
    {
        return count($this->selected) >= 5;
    }

    public function submit()
    {
        if (!$this->canContinue()) {
            return;
        }

        $user = auth()->user();
        $user->interests()->syncWithPivotValues($this->selected, ['weight' => 1]);
        $user->new_account = false;
        $user->save();

        return redirect('/');
    }

    public function with()
    {
        return [
            'categories' => Category::query()->orderBy('name')->paginate(20),
        ];
    }
};
?>

<div
     class="flex min-h-screen items-center justify-center bg-slate-50 px-6 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="w-full max-w-4xl">

        {{-- Header --}}
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                Choose your interests
            </h1>
            <p class="mt-2 text-slate-600 dark:text-slate-400">
                Select at least <span class="font-semibold text-blue-600 dark:text-blue-400">5 categories</span> to
                continue
            </p>
        </div>

        {{-- Categories Grid (5 rows x 4 cols = 20 items) --}}
        <div class="grid grid-cols-4 gap-3">
            @foreach ($categories as $category)
                <button class="group inline-flex items-center justify-center rounded-full border px-4 py-2 text-sm font-medium transition-all duration-200"
                        type="button"
                        wire:click="toggle({{ $category->id }})"
                        @class([
                            'bg-blue-600 text-white border-blue-500 shadow-lg shadow-blue-500/20 hover:bg-blue-700' => $this->isSelected(
                                $category->id),
                            'bg-slate-100 text-slate-700 border-slate-300 hover:border-blue-500 hover:text-slate-900 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700 dark:hover:text-white' => !$this->isSelected(
                                $category->id),
                        ])>
                    @if ($this->isSelected($category->id))
                        <span
                              class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-white/15 text-white">
                            <svg class="h-4 w-4"
                                 xmlns="http://www.w3.org/2000/svg"
                                 viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M16.704 5.292a1 1 0 0 1 0 1.416l-7.378 7.378a1 1 0 0 1-1.415 0l-3.102-3.102a1 1 0 1 1 1.414-1.414l2.395 2.395 6.67-6.67a1 1 0 0 1 1.416 0z"
                                      clip-rule="evenodd" />
                            </svg>
                        </span>
                    @endif
                    {{ $category->name }}
                </button>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $categories->links() }}
        </div>

        {{-- Footer --}}
        <div class="mt-10 text-center">

            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                Selected:
                <span class="font-semibold text-blue-600 dark:text-blue-400">
                    {{ count($selected) }}
                </span> / 5
            </p>

            <button class="{{ $this->canContinue() ? 'bg-blue-600 hover:bg-blue-700 text-white cursor-pointer' : 'bg-gray-800 text-gray-500 cursor-not-allowed' }} rounded-full px-6 py-3 font-semibold transition-all duration-200"
                    wire:click="submit"
                    @disabled(!$this->canContinue())
                    @class([
                        'bg-blue-600 hover:bg-blue-700 text-white' => $this->canContinue(),
                        'bg-gray-800 text-gray-500 cursor-not-allowed' => !$this->canContinue(),
                    ])>
                Go to Home
            </button>
        </div>

    </div>
</div>
