<?php

namespace Database\Seeders;

use App\Enums\InteractionTypeEnum;
use App\Enums\InteractionWeightEnum;
use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class InteractionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // CRITICAL: Keeps the interaction matrix identical on every seed run
        srand(67890);

        $users = User::all();
        $posts = Post::all();

        if ($users->count() < 5 || $posts->count() < 10) {
            $this->command->error('Please ensure you have seeded users and posts first.');

            return;
        }

        // --- DESIGNING A PREDETERMINED SCENARIO FOR YOUR SHOWCASE ---
        // Let's force User 1 and User 2 to have highly overlapping tastes (The "Peer" Cluster)
        // They both highly engage with Posts 1, 2, and 3.
        $targetPostsforA = $posts->take(3);
        $targetPostsforB = $posts->take(3);
        $targetUserA = User::where('email', '=', 'usera@gmail.com', 'and')->first();
        $targetUserB = User::where('email', '=', 'userb@gmail.com', 'and')->first();

        foreach ($targetPostsforA as $post) {
            Interaction::create([
                'user_id' => $targetUserA->id,
                'post_id' => $post->id,
                'interaction_type' => InteractionTypeEnum::LIKE->value,
                'weight' => InteractionWeightEnum::forType(InteractionTypeEnum::LIKE->value),
            ]);
        }


        // Now, User 1 also likes Post 4.
        // Because User 2 is heavily matched with User 1, your CF algorithm should recommend Post 4 to User 2!
        if ($posts->has(3)) {
            Interaction::create([
                'user_id' => 1,
                'post_id' => $posts->get(3)->id, // Post 4
                'interaction_type' => InteractionTypeEnum::SHARE->value,
                'weight' => InteractionWeightEnum::forType(InteractionTypeEnum::SHARE->value),
            ]);
        }

        // --- SEED THE REST OF THE MATRIX DETERMINISTICALLY ---
        // Populate background noise for other users so the engine has to filter through data
        foreach ($users->skip(2) as $user) {
            // Each background user interacts with 5 predictable posts
            $randomPosts = $posts->random(5);

            foreach ($randomPosts as $post) {
                $type = collect([InteractionTypeEnum::VIEW, InteractionTypeEnum::LIKE, InteractionTypeEnum::COMMENT])->random();
                $weight = InteractionWeightEnum::forType($type->value);

                Interaction::create([
                    'user_id' => $user->id,
                    'post_id' => $post->id,
                    'interaction_type' => $type->value,
                    'weight' => $weight,
                ]);
            }
        }
    }
}
