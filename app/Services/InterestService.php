<?php

namespace App\Services;

use App\Enums\InteractionWeightEnum;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InterestService
{
    public function updateInterest(int $postId, string $interactionType, bool $isPositiveInteraction): void
    {
        $userId = Auth::user()->id;
        $post = Post::with('categories')->find($postId);

        $weightValue = InteractionWeightEnum::forType($interactionType);

        if (! $post || $post->categories->isEmpty()) {
            return;
        }
        // Wrap operations in a transaction for database integrity
        DB::transaction(function () use ($userId, $post, $weightValue, $isPositiveInteraction) {
            foreach ($post->categories as $category) {

                // Fetch existing entry or default values
                $interest = DB::table('interests')
                    ->where('user_id', $userId)
                    ->where('category_id', $category->id)
                    ->first();

                if ($interest) {
                    if ($isPositiveInteraction) {
                        // Accumulate weight over time
                        DB::table('interests')
                            ->where('id', $interest->id)
                            ->update([
                                'weight' => $interest->weight + $weightValue,
                                'updated_at' => now(),
                            ]);
                    } else {
                        // Decrease weight for negative interactions, but not below zero
                        $newWeight = max(0, $interest->weight - $weightValue);
                        DB::table('interests')
                            ->where('id', $interest->id)
                            ->update([
                                'weight' => $newWeight,
                                'updated_at' => now(),
                            ]);
                    }
                } else {
                    // Create initial profile anchor
                    DB::table('interests')->insert([
                        'user_id' => $userId,
                        'category_id' => $category->id,
                        'weight' => $weightValue,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
