<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'title' => str(fake()->sentence(4))->beforeLast('.'),
            'content' => fake()->paragraphs(rand(3, 7), true),
            'image_url' => 'https://picsum.photos/seed/'.fake()->uuid().'/800/600',
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
