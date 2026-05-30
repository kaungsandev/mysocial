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
        $categories = Category::all();
        $users = User::all();

        Post::factory()
            ->count(500)
            ->recycle($categories)
            ->create()
            ->each(function (Post $post) use ($users) {
                // Attach 1 to 3 random users to each post
                $post->users()->attach($users->random(rand(1, 3)));
            });
    }
}
