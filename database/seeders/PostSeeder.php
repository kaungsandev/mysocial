<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // the random seed for reproducibility
        srand(12345);
        fake()->seed(12345);
        $categories = Category::all();
        $users = User::all();

        Post::factory()
            ->count(100)
            ->create()
            ->each(function (Post $post) use ($users, $categories) {
                // Always attaches the same "random" categories
                $post->categories()->attach(
                    $categories->random(rand(1, 2))->pluck('id')->toArray()
                );

                // Always attaches the same "random" authors
                $post->users()->attach(
                    $users->random(1)->pluck('id')->toArray()
                );
            });
    }
}
