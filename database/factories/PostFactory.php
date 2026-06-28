<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'title' => str(fake()->sentence(4))->beforeLast('.'),
            'content' => fake()->paragraphs(rand(3, 7), true),
            'image_url' => 'https://picsum.photos/seed/'.fake()->uuid().'/800/600',
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
