<x-layouts::app.header :title="$title ?? null">
    <flux:main class="p-0!">
        <form class="flex min-h-screen items-center justify-center bg-gray-50 px-6 text-white dark:bg-gray-950"
              action="{{ route('algorithm.select.store') }}"
              method="POST"
              x-data="{ selected: '{{ old('algorithm') }}' }">
            @csrf

            <div class="w-full max-w-xl">

                {{-- Title --}}
                <div class="mb-10 text-center">
                    <h1 class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">
                        Which do you like?
                    </h1>

                    <p class="mt-3 text-lg text-zinc-600 dark:text-zinc-400">
                        Choose one option to personalize your experience.
                    </p>
                </div>

                {{-- Hidden Input --}}
                <input name="algorithm"
                       type="hidden"
                       x-model="selected">

                {{-- Options --}}
                <div class="space-y-4">

                    {{-- Content Based --}}
                    <button class="flex w-full items-center justify-between rounded-2xl border p-6 text-left transition-all duration-200"
                            type="button"
                            @click="selected = 'content'"
                            :class="selected === 'content'
                                ?
                                'border-blue-500 bg-blue-50 ring-2 ring-blue-500 dark:bg-blue-500/10' :
                                'border-zinc-200 bg-white hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800/50'">
                        <div>
                            <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">
                                Content-Based Recommendations
                            </h2>

                            <p class="mt-2 text-zinc-600 dark:text-zinc-400">
                                Discover content based on your interests.
                            </p>
                        </div>

                        <div class="flex h-6 w-6 items-center justify-center rounded-full border transition"
                             :class="selected === 'content'
                                 ?
                                 'border-blue-500 bg-blue-500' :
                                 'border-zinc-400 dark:border-zinc-600'">
                            <div class="h-2.5 w-2.5 rounded-full bg-white"
                                 x-show="selected === 'content'"
                                 x-transition></div>
                        </div>
                    </button>

                    {{-- Collaborative --}}
                    <button class="flex w-full items-center justify-between rounded-2xl border p-6 text-left transition-all duration-200"
                            type="button"
                            @click="selected = 'collaborative'"
                            :class="selected === 'collaborative'
                                ?
                                'border-blue-500 bg-blue-50 ring-2 ring-blue-500 dark:bg-blue-500/10' :
                                'border-zinc-200 bg-white hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800/50'">
                        <div>
                            <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">
                                Collaborative Recommendations
                            </h2>

                            <p class="mt-2 text-zinc-600 dark:text-zinc-400">
                                Discover content based on what others like.
                            </p>
                        </div>

                        <div class="flex h-6 w-6 items-center justify-center rounded-full border transition"
                             :class="selected === 'collaborative'
                                 ?
                                 'border-blue-500 bg-blue-500' :
                                 'border-zinc-400 dark:border-zinc-600'">
                            <div class="h-2.5 w-2.5 rounded-full bg-white"
                                 x-show="selected === 'collaborative'"
                                 x-transition></div>
                        </div>
                    </button>

                </div>

                {{-- Validation --}}
                @error('algorithm')
                    <p class="mt-4 text-sm text-red-600 dark:text-red-400">
                        {{ $message }}
                    </p>
                @enderror

                {{-- Continue --}}
                <button class="mt-10 flex w-full items-center justify-center gap-2 rounded-xl px-6 py-4 font-semibold transition-all duration-200"
                        type="submit"
                        :disabled="!selected"
                        :class="selected
                            ?
                            'bg-blue-600 text-white hover:bg-blue-500' :
                            'cursor-not-allowed bg-zinc-200 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500'">
                    <span>Continue</span>

                    <svg class="h-5 w-5"
                         xmlns="http://www.w3.org/2000/svg"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke="currentColor"
                         stroke-width="2">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M5 12h14m-6-6l6 6-6 6" />
                    </svg>
                </button>

            </div>
        </form>
    </flux:main>
</x-layouts::app.header>
