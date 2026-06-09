<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InteractionFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'type' => fake()->randomElement(['view', 'like', 'share', 'rate']),
            'weight' => fake()->numberBetween(-10000, 10000),
        ];
    }
}
