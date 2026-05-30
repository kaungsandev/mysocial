<x-layouts::app.header :title="$title ?? null">
    <flux:main>
        <div class="container mx-auto grid grid-cols-1 gap-6 p-4 md:grid-cols-12">
            <!-- Profile Sidebar (Left) -->
            <div class="md:col-span-3">
                <livewire:home.profile-card />
            </div>

            <!-- Feed (Center) -->
            <div class="space-y-6 md:col-span-6">
                <livewire:home.create-post />
                <livewire:home.feed />
            </div>

            <!-- Suggestions/Trending (Right) -->
            <div class="md:col-span-3">
                <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <h3 class="border-b pb-2 font-bold">Recommended for You</h3>
                    <p class="mt-2 text-xs text-gray-500">Based on your shared interests with other users.</p>
                </div>
            </div>
        </div>

    </flux:main>
</x-layouts::app.header>
