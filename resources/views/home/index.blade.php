<x-layouts::app.header :title="$title ?? null">
    <flux:main class="p-0! w-full">
        <div class="flex h-[calc(100vh-3.5rem)] justify-center">
            <!-- Left Sidebar: Profile + Create Post -->
            <div
                 class="w-full space-y-4 overflow-y-auto border-r border-zinc-200 bg-zinc-50 p-4 md:w-80 md:flex-none dark:border-zinc-700 dark:bg-zinc-900">
                <livewire:home.profile-card />
                <livewire:home.create-post />
            </div>

            <!-- Feed (Center) -->
            <div class="flex max-w-lg flex-1 flex-col">
                <livewire:home.feed />
            </div>

            <!-- Stats (Right) -->
            <div
                 class="flex overflow-y-auto border-l border-zinc-200 bg-zinc-50 p-4 md:block dark:border-zinc-700 dark:bg-zinc-900">
                <!-- Refresh Button -->
                <button class="mb-3 flex w-full items-center justify-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow transition hover:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                        onclick="Livewire.dispatch('refresh-feed')">
                    <flux:icon class="h-4 w-4"
                               name="arrow-path" />
                    Refresh Feed
                </button>

                <div class="flex flex-row justify-between gap-4">
                    <livewire:widgets.category-rating />
                    <livewire:widgets.recommendation-stats />
                </div>

                <!-- Scroll to Top Button -->
                <button class="fixed bottom-6 right-6 flex h-10 w-10 items-center justify-center rounded-full bg-zinc-900 text-white shadow-lg transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                        onclick="Livewire.dispatch('scroll-to-top')">
                    <flux:icon class="h-5 w-5"
                               name="arrow-up" />
                </button>
            </div>
        </div>
    </flux:main>
</x-layouts::app.header>
