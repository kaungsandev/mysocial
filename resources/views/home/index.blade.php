<x-layouts::app.header :title="$title ?? null">
    <flux:main class="p-0!">
        <div class="container mx-auto flex h-[calc(100vh-3.5rem)]">
            <!-- Left Sidebar: Profile + Create Post -->
            <div
                 class="w-full space-y-4 overflow-y-auto border-r border-zinc-200 bg-zinc-50 p-4 md:w-80 md:flex-none dark:border-zinc-700 dark:bg-zinc-900">
                <livewire:home.profile-card />
                <livewire:home.create-post />
            </div>

            <!-- Feed (Center) -->
            <div class="flex min-w-0 flex-1 flex-col">
                <livewire:home.feed />
            </div>

            <!-- Suggestions/Trending (Right) -->
            <div class="hidden w-full overflow-y-auto border-l border-zinc-200 bg-zinc-50 p-4 md:block md:w-80 md:flex-none dark:border-zinc-700 dark:bg-zinc-900">
                <!-- Refresh Button -->
                <button onclick="Livewire.dispatch('refresh-feed')"
                        class="mb-3 flex w-full items-center justify-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow transition hover:bg-zinc-100 dark:bg-gray-800 dark:text-zinc-300 dark:hover:bg-gray-700">
                    <flux:icon name="arrow-path" class="h-4 w-4" />
                    Refresh Feed
                </button>

                <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <h3 class="border-b pb-2 font-bold">Recommended for You</h3>
                    <p class="mt-2 text-xs text-gray-500">Based on your shared interests with other users.</p>
                </div>

                <!-- Scroll to Top Button -->
                <button onclick="Livewire.dispatch('scroll-to-top')"
                        class="fixed bottom-6 right-6 flex h-10 w-10 items-center justify-center rounded-full bg-zinc-900 text-white shadow-lg transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                    <flux:icon name="arrow-up" class="h-5 w-5" />
                </button>
            </div>
        </div>
    </flux:main>
</x-layouts::app.header>
