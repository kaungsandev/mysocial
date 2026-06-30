<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Services\InterActionService;
class PostSeeder extends Seeder
{
    private const POSTS_PER_CATEGORY = 50;

    public function run(InterActionService $interActionService): void
    {
        srand(12345);
        fake()->seed(12345);

        $categories = Category::all();
        $users = User::all();

        $posts = collect();

        // Create 50 posts for every category.
        foreach ($categories as $category) {

            $createdPosts = Post::factory()
                ->count(self::POSTS_PER_CATEGORY)
                ->create();

            foreach ($createdPosts as $post) {
                $post->categories()->attach($category->id);
                $randomUser = $users->random();
                $post->users()->attach($randomUser->id);
                $interActionService->recordInteraction(
                    postId: $post->id,
                    userId: $randomUser->id,
                    interactionType: \App\Enums\InteractionTypeEnum::POST->value
                );
            }

            $posts = $posts->merge($createdPosts);
        }

        // Give about 30% of posts a second category.
        foreach ($posts->random((int) ($posts->count() * 0.3)) as $post) {

            $currentCategoryId = $post->categories()->value('category_id');

            $secondCategory = $categories
                ->where('id', '!=', $currentCategoryId)
                ->random();

            $post->categories()->attach($secondCategory->id);
        }
    }
}
